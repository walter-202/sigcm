<?php

namespace Tests\Feature;

use App\Models\MedicalConsultation;
use App\Models\User;
use App\Services\SigcmDatabaseService;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Throwable;

class SigcmDatabaseAndFilamentTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['database.default' => 'pgsql']);
        DB::purge('pgsql');
        DB::purge('pgsql_asistente');
    }

    public function test_scripts_sql_estan_consolidados_en_carpeta_sql(): void
    {
        $schemaScript = base_path('sql/01_base_de_datos_e_inserts.sql');
        $db2Script = base_path('sql/02_triggers_procedimientos_vistas_dcl.sql');

        $this->assertFileExists($schemaScript);
        $this->assertFileExists($db2Script);
        $this->assertFileDoesNotExist(base_path('base_de_datos.sql'));

        $this->assertStringContainsString('CREATE TABLE users', (string) file_get_contents($schemaScript));
        $this->assertStringContainsString('CREATE PROCEDURE sp_registrar_y_firmar_consulta_segura', (string) file_get_contents($db2Script));
    }

    public function test_asistente_puede_leer_las_tres_vistas_publicas(): void
    {
        $service = app(SigcmDatabaseService::class);

        $medicos = $service->getCatalogoMedicos();
        $agenda = $service->getAgendaConsultasDetalle();
        $expedientes = $service->getExpedientesResumen();

        $this->assertNotEmpty($medicos);
        $this->assertNotEmpty($agenda);
        $this->assertNotEmpty($expedientes);
    }

    public function test_asistente_es_bloqueado_en_audit_events_por_dcl(): void
    {
        $result = app(SigcmDatabaseService::class)->probarAccesoRestringidoBitacora();

        $this->assertTrue($result['blocked']);
        $this->assertSame('42501', $result['sqlstate']);
        $this->assertStringContainsString('audit_events', $result['message']);
    }

    public function test_cursor_valoriza_actividad_clinica(): void
    {
        $cursor = app(SigcmDatabaseService::class)->valorizarActividadClinicaCursor();

        $this->assertGreaterThanOrEqual(10, $cursor['consultas_firmadas']);
        $this->assertGreaterThanOrEqual(2, $cursor['citas_activas']);
        $this->assertGreaterThan(0, (float) $cursor['horas_agenda']);
    }

    public function test_procedimiento_firma_consulta_y_registra_auditoria_en_transaccion(): void
    {
        $service = app(SigcmDatabaseService::class);
        $connection = DB::connection('pgsql');
        $connection->beginTransaction();

        try {
            $beforeAuditCount = (int) $connection->table('audit_events')->count();
            $beforeConsultationsCount = (int) $connection->table('medical_consultations')->count();

            $service->registrarYFirmarConsultaSegura([
                'patient_id' => 1,
                'doctor_id' => 1,
                'appointment_id' => null,
                'chief_complaint' => 'Control clínico de prueba automatizada',
                'subjective' => 'Paciente asintomático',
                'objective' => 'Signos vitales estables',
                'assessment' => 'Evolución favorable',
                'plan' => 'Continuar seguimiento ambulatorio',
                'episode_title' => 'Episodio de prueba PHPUnit',
            ], 1);

            $afterConsultationsCount = (int) $connection->table('medical_consultations')->count();
            $afterAuditCount = (int) $connection->table('audit_events')->count();
            $lastConsultationAudit = $connection->table('audit_events')
                ->where('entity_name', 'medical_consultations')
                ->orderByDesc('id')
                ->first();

            $this->assertSame($beforeConsultationsCount + 1, $afterConsultationsCount);
            $this->assertGreaterThan($beforeAuditCount, $afterAuditCount);
            $this->assertNotNull($lastConsultationAudit);
            $this->assertSame('SIGN', $lastConsultationAudit->operation);
        } finally {
            $connection->rollBack();
        }
    }

    public function test_trigger_impide_modificar_consulta_firmada(): void
    {
        $service = app(SigcmDatabaseService::class);
        $signedId = (int) DB::connection('pgsql')
            ->table('medical_consultations')
            ->where('status', 'signed')
            ->value('id');

        $this->assertGreaterThan(0, $signedId);

        $caughtMessage = '';
        try {
            $service->probarInmutabilidadConsultaFirmada($signedId);
        } catch (Throwable $exception) {
            $caughtMessage = $service->formatDatabaseException($exception);
        }

        $this->assertStringContainsString('Error de integridad clínica', $caughtMessage);
    }

    public function test_trigger_impide_solapamiento_de_citas_del_mismo_medico(): void
    {
        $service = app(SigcmDatabaseService::class);
        $connection = DB::connection('pgsql');
        $connection->beginTransaction();

        try {
            $baseAppointment = $connection->table('appointments')
                ->whereIn('status', ['pending', 'confirmed', 'checked_in'])
                ->orderBy('id')
                ->first();

            $this->assertNotNull($baseAppointment);

            $secondId = (int) $connection->table('appointments')->insertGetId([
                'patient_id' => 2,
                'doctor_id' => $baseAppointment->doctor_id,
                'created_by_user_id' => 1,
                'starts_at' => '2026-12-01 14:00:00-04',
                'ends_at' => '2026-12-01 15:00:00-04',
                'status' => 'confirmed',
                'reason' => 'Cita sin solapamiento para test',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $caughtMessage = '';
            try {
                $service->reprogramarCitaSegura(
                    appointmentId: $secondId,
                    changedByUserId: 1,
                    newStart: (string) $baseAppointment->starts_at,
                    newEnd: (string) $baseAppointment->ends_at,
                    reason: 'Prueba de solapamiento esperado'
                );
            } catch (Throwable $exception) {
                $caughtMessage = $service->formatDatabaseException($exception);
            }

            $this->assertStringContainsString('Error de integridad en agenda', $caughtMessage);
        } finally {
            $connection->rollBack();
        }
    }

    public function test_panel_filament_usa_idioma_espanol_y_terminologia_empresarial(): void
    {
        $this->assertSame('es', app()->getLocale());

        $this->get('/admin/login')
            ->assertOk()
            ->assertSee('lang="es"', false)
            ->assertSee('Entre a su cuenta')
            ->assertSee('Correo electrónico')
            ->assertSee('Entrar');

        $admin = User::query()->where('email', 'admin@sigcm.local')->first();

        $this->assertNotNull($admin);

        $this->actingAs($admin)
            ->get('/admin')
            ->assertOk()
            ->assertSee('SIGCM · Gestión Clínica Médica')
            ->assertSee('Resumen clínico operativo')
            ->assertDontSee('Laboratorio BD II')
            ->assertDontSee('Arquitectura implementada en la materia');

        $this->actingAs($admin)
            ->get('/admin/bitacora-auditoria')
            ->assertOk()
            ->assertSee('Control de acceso restringido verificado')
            ->assertSee('42501')
            ->assertDontSee('Laboratorio BD II');
    }

    public function test_exclusion_constraint_rechaza_citas_activas_solapadas_para_un_mismo_medico(): void
    {
        $connection = DB::connection('pgsql');
        $connection->beginTransaction();

        try {
            $connection->table('appointments')->insert([
                'patient_id' => 1,
                'doctor_id' => 1,
                'created_by_user_id' => 1,
                'starts_at' => '2026-12-10 09:00:00-04',
                'ends_at' => '2026-12-10 09:30:00-04',
                'status' => 'confirmed',
                'reason' => 'Cita base de prueba GiST',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $caught = false;

            try {
                $connection->transaction(function () use ($connection): void {
                    $connection->table('appointments')->insert([
                        'patient_id' => 2,
                        'doctor_id' => 1,
                        'created_by_user_id' => 1,
                        'starts_at' => '2026-12-10 09:15:00-04',
                        'ends_at' => '2026-12-10 09:45:00-04',
                        'status' => 'pending',
                        'reason' => 'Solapamiento prohibido',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                });
            } catch (Throwable) {
                $caught = true;
            }

            $this->assertTrue($caught);

            $cancelledId = (int) $connection->table('appointments')->insertGetId([
                'patient_id' => 2,
                'doctor_id' => 1,
                'created_by_user_id' => 1,
                'starts_at' => '2026-12-10 09:15:00-04',
                'ends_at' => '2026-12-10 09:45:00-04',
                'status' => 'cancelled',
                'reason' => 'Cita cancelada permitida en el mismo bloque',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $this->assertGreaterThan(0, $cancelledId);
        } finally {
            $connection->rollBack();
        }
    }

    public function test_enmiendas_y_bitacora_son_inmutables_append_only(): void
    {
        $connection = DB::connection('pgsql');
        $connection->beginTransaction();

        try {
            $amendment = $connection->table('consultation_amendments')->first();
            $auditEvent = $connection->table('audit_events')->first();

            $this->assertNotNull($amendment);
            $this->assertNotNull($auditEvent);

            $caughtAmendmentUpdate = false;

            try {
                $connection->transaction(function () use ($connection, $amendment): void {
                    $connection->table('consultation_amendments')
                        ->where('id', $amendment->id)
                        ->update(['reason' => 'Intento de alteración']);
                });
            } catch (Throwable $exception) {
                $caughtAmendmentUpdate = str_contains($exception->getMessage(), 'append-only');
            }

            $caughtAuditDelete = false;

            try {
                $connection->transaction(function () use ($connection, $auditEvent): void {
                    $connection->table('audit_events')
                        ->where('id', $auditEvent->id)
                        ->delete();
                });
            } catch (Throwable $exception) {
                $caughtAuditDelete = str_contains($exception->getMessage(), 'append-only');
            }

            $this->assertTrue($caughtAmendmentUpdate);
            $this->assertTrue($caughtAuditDelete);
        } finally {
            $connection->rollBack();
        }
    }

    public function test_consulta_firmada_protege_diagnosticos_y_sp_firmar_consulta_existente_funciona(): void
    {
        $connection = DB::connection('pgsql');
        $service = app(SigcmDatabaseService::class);
        $connection->beginTransaction();

        try {
            $signedConsultation = $connection->table('medical_consultations')
                ->where('status', 'signed')
                ->first();

            $this->assertNotNull($signedConsultation);

            $caughtSignedDiagnosis = false;

            try {
                $connection->transaction(function () use ($connection, $signedConsultation): void {
                    $connection->table('consultation_diagnoses')->insert([
                        'consultation_id' => $signedConsultation->id,
                        'code_system' => 'CIE-10',
                        'code' => 'I10',
                        'diagnosis_text' => 'Intento fuera de borrador',
                        'certainty' => 'confirmed',
                        'is_primary' => false,
                        'created_at' => now(),
                    ]);
                });
            } catch (Throwable $exception) {
                $caughtSignedDiagnosis = str_contains($exception->getMessage(), 'ya está firmada');
            }

            $this->assertTrue($caughtSignedDiagnosis);

            $draftConsultationId = (int) $connection->table('medical_consultations')->insertGetId([
                'patient_id' => 1,
                'doctor_id' => 1,
                'occurred_at' => now()->subMinutes(10),
                'chief_complaint' => 'Control de presión arterial para firma',
                'subjective' => 'Sin cefalea actual',
                'objective' => 'PA 125/80 mmHg',
                'assessment' => 'Hipertensión controlada',
                'plan' => 'Mantener esquema y control mensual',
                'status' => 'draft',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $connection->table('consultation_diagnoses')->insert([
                'consultation_id' => $draftConsultationId,
                'code_system' => 'CIE-10',
                'code' => 'I10',
                'diagnosis_text' => 'Hipertensión esencial controlada',
                'certainty' => 'confirmed',
                'is_primary' => true,
                'created_at' => now(),
            ]);

            $draftModel = MedicalConsultation::query()->findOrFail($draftConsultationId);
            $service->firmarConsultaExistente($draftModel, 1, 'Episodio de control desde borrador');

            $signedRow = $connection->table('medical_consultations')->where('id', $draftConsultationId)->first();
            $this->assertSame('signed', $signedRow?->status);
            $this->assertNotNull($signedRow?->signed_at);
        } finally {
            $connection->rollBack();
        }
    }

    public function test_consistencia_de_paciente_entre_episodio_y_consultas(): void
    {
        $connection = DB::connection('pgsql');
        $connection->beginTransaction();

        try {
            $episodePatientOne = $connection->table('care_episodes')
                ->join('clinical_records', 'clinical_records.id', '=', 'care_episodes.clinical_record_id')
                ->where('clinical_records.patient_id', 1)
                ->select('care_episodes.id as episode_id')
                ->first();

            $consultationPatientTwo = $connection->table('medical_consultations')
                ->where('patient_id', 2)
                ->first();

            $this->assertNotNull($episodePatientOne);
            $this->assertNotNull($consultationPatientTwo);

            $caughtMismatch = false;

            try {
                $connection->transaction(function () use ($connection, $episodePatientOne, $consultationPatientTwo): void {
                    $connection->table('episode_consultations')->insert([
                        'episode_id' => $episodePatientOne->episode_id,
                        'consultation_id' => $consultationPatientTwo->id,
                        'relation_type' => 'related',
                        'created_at' => now(),
                    ]);
                });
            } catch (Throwable $exception) {
                $caughtMismatch = str_contains($exception->getMessage(), 'no corresponde al paciente del episodio');
            }

            $this->assertTrue($caughtMismatch);
        } finally {
            $connection->rollBack();
        }
    }

    public function test_sp_sustituir_receta_segura_reemplaza_receta_emitida_y_registra_auditoria(): void
    {
        $connection = DB::connection('pgsql');
        $service = app(SigcmDatabaseService::class);
        $connection->beginTransaction();

        try {
            $issuedPrescription = $connection->table('prescriptions')
                ->where('status', 'issued')
                ->first();

            $this->assertNotNull($issuedPrescription);

            $newPrescriptionId = $service->sustituirRecetaSegura(
                oldPrescriptionId: (int) $issuedPrescription->id,
                actorUserId: 1,
                notes: 'Ajuste terapéutico por tolerancia'
            );

            $oldAfter = $connection->table('prescriptions')->where('id', $issuedPrescription->id)->first();
            $newAfter = $connection->table('prescriptions')->where('id', $newPrescriptionId)->first();

            $this->assertSame('replaced', $oldAfter?->status);
            $this->assertSame('draft', $newAfter?->status);
            $this->assertSame((int) $issuedPrescription->id, (int) $newAfter?->supersedes_prescription_id);

            $auditLogged = $connection->table('audit_events')
                ->where('entity_name', 'prescriptions')
                ->where('entity_id', $newPrescriptionId)
                ->where('operation', 'INSERT')
                ->exists();

            $this->assertTrue($auditLogged);
        } finally {
            $connection->rollBack();
        }
    }
}
