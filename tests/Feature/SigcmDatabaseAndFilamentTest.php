<?php

namespace Tests\Feature;

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
            $lastAudit = $connection->table('audit_events')->orderByDesc('id')->first();

            $this->assertSame($beforeConsultationsCount + 1, $afterConsultationsCount);
            $this->assertGreaterThan($beforeAuditCount, $afterAuditCount);
            $this->assertNotNull($lastAudit);
            $this->assertSame('SIGN', $lastAudit->operation);
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

    public function test_panel_filament_y_tres_vistas_iniciales_responden_correctamente(): void
    {
        $admin = User::query()->where('email', 'admin@sigcm.local')->first();

        $this->assertNotNull($admin);

        $this->actingAs($admin)
            ->get('/admin')
            ->assertOk()
            ->assertSee('Resumen ejecutivo en tiempo real')
            ->assertSee('Arquitectura implementada en la materia');

        $this->actingAs($admin)
            ->get('/admin/catalogo-medicos')
            ->assertOk()
            ->assertSee('vw_catalogo_medicos_publico')
            ->assertSee('vw_expedientes_episodios_resumen');

        $this->actingAs($admin)
            ->get('/admin/agenda-consultas')
            ->assertOk()
            ->assertSee('vw_agenda_consultas_detalle');

        $this->actingAs($admin)
            ->get('/admin/bitacora-auditoria')
            ->assertOk()
            ->assertSee('Bloqueo de seguridad verificado')
            ->assertSee('42501');
    }
}
