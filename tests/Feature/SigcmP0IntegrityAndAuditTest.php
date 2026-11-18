<?php

namespace Tests\Feature;

use App\Enums\AppointmentStatus;
use App\Enums\ConsultationStatus;
use App\Enums\EpisodeStatus;
use App\Models\Appointment;
use App\Models\AuditEvent;
use App\Models\CareEpisode;
use App\Models\ClinicalDocument;
use App\Models\DiagnosticReport;
use App\Models\MedicalConsultation;
use App\Models\Patient;
use App\Models\PatientCondition;
use App\Models\Prescription;
use App\Models\User;
use App\Services\SigcmDatabaseService;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Tests\TestCase;
use Throwable;

class SigcmP0IntegrityAndAuditTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['database.default' => 'pgsql']);
        DB::purge('pgsql');
        DB::purge('pgsql_asistente');
    }

    public function test_traducciones_laravel_es_y_enums_localizados(): void
    {
        $this->assertSame('SIGCM · Gestión Clínica Médica', __('sigcm.brand_name'));
        $this->assertSame('Nota Clínica de Atención Externa', __('sigcm.consultation.sections.clinical_note'));
        $this->assertSame('Pendiente', AppointmentStatus::Pending->getLabel());
        $this->assertSame('Firmada', ConsultationStatus::Signed->getLabel());
        $this->assertSame('Activo', EpisodeStatus::Active->getLabel());
    }

    public function test_auditoria_registra_actor_user_id_real_en_tablas_criticas_y_dcl_protege_documentos(): void
    {
        $medico = User::query()->where('email', 'medico@sigcm.local')->firstOrFail();
        $connection = DB::connection('pgsql');
        $connection->beginTransaction();

        try {
            $this->actingAs($medico);

            $patient = Patient::factory()->create();
            $condition = PatientCondition::factory()->create(['patient_id' => $patient->id]);
            $document = ClinicalDocument::factory()->create([
                'patient_id' => $patient->id,
                'uploaded_by_user_id' => $medico->id,
            ]);

            $episode = CareEpisode::query()->where('status', 'active')->firstOrFail();
            $episode->update([
                'status' => EpisodeStatus::Resolved,
                'closed_at' => now(),
            ]);

            $auditedEntities = [
                ['entity' => 'patients', 'id' => $patient->id, 'op' => 'INSERT'],
                ['entity' => 'patient_conditions', 'id' => $condition->id, 'op' => 'INSERT'],
                ['entity' => 'clinical_documents', 'id' => $document->id, 'op' => 'INSERT'],
                ['entity' => 'care_episodes', 'id' => $episode->id, 'op' => 'UPDATE'],
            ];

            foreach ($auditedEntities as $expected) {
                $event = AuditEvent::query()
                    ->where('entity_name', $expected['entity'])
                    ->where('entity_id', $expected['id'])
                    ->where('operation', $expected['op'])
                    ->orderByDesc('id')
                    ->first();

                $this->assertNotNull($event, "Debe existir auditoría para {$expected['entity']}");
                $this->assertSame($medico->id, $event->actor_user_id);
            }

            $blockedDocumentRead = false;

            try {
                DB::connection('pgsql_asistente')->select('SELECT id FROM clinical_documents LIMIT 1');
            } catch (Throwable $exception) {
                $blockedDocumentRead = (string) $exception->getCode() === '42501';
            }

            $this->assertTrue($blockedDocumentRead);
        } finally {
            $connection->rollBack();
        }
    }

    public function test_transiciones_de_cita_emision_de_receta_y_revision_de_informe(): void
    {
        $admin = User::query()->where('email', 'admin@sigcm.local')->firstOrFail();
        $service = app(SigcmDatabaseService::class);
        $connection = DB::connection('pgsql');
        $connection->beginTransaction();

        try {
            $pendingAppointment = Appointment::factory()->create([
                'status' => AppointmentStatus::Pending,
                'starts_at' => '2026-12-20 08:00:00-04',
                'ends_at' => '2026-12-20 08:30:00-04',
            ]);

            $confirmed = $service->confirmarCita($pendingAppointment, $admin->id);
            $this->assertSame(AppointmentStatus::Confirmed, $confirmed->status);

            $checkedIn = $service->registrarLlegadaCita($confirmed, $admin->id);
            $this->assertSame(AppointmentStatus::CheckedIn, $checkedIn->status);

            $cancelled = $service->cancelarCita($checkedIn, $admin->id);
            $this->assertSame(AppointmentStatus::Cancelled, $cancelled->status);

            $noShowBase = Appointment::factory()->create([
                'status' => AppointmentStatus::Confirmed,
                'starts_at' => '2026-12-20 09:00:00-04',
                'ends_at' => '2026-12-20 09:30:00-04',
            ]);
            $noShow = $service->marcarNoAsistioCita($noShowBase, $admin->id);
            $this->assertSame(AppointmentStatus::NoShow, $noShow->status);

            $completed = Appointment::factory()->create([
                'status' => AppointmentStatus::Completed,
                'starts_at' => '2026-12-20 10:00:00-04',
                'ends_at' => '2026-12-20 10:30:00-04',
            ]);

            $rejectedCompletedCancel = false;

            try {
                $service->cancelarCita($completed, $admin->id);
            } catch (InvalidArgumentException) {
                $rejectedCompletedCancel = true;
            }

            $this->assertTrue($rejectedCompletedCancel);

            $draftPrescription = Prescription::factory()->create(['status' => 'draft']);
            $issued = $service->emitirReceta($draftPrescription, $admin->id);
            $this->assertSame('issued', $issued->status);
            $this->assertNotNull($issued->issued_at);

            $receivedReport = DiagnosticReport::factory()->create(['status' => 'received']);
            $reviewed = $service->revisarInformeDiagnostico($receivedReport, $admin->id, 'Resultados dentro de rango esperado.');
            $this->assertSame('reviewed', $reviewed->status);
            $this->assertSame($admin->id, $reviewed->reviewed_by_user_id);
            $this->assertNotNull($reviewed->reviewed_at);
        } finally {
            $connection->rollBack();
        }
    }

    public function test_politicas_rbac_diferencian_super_admin_medico_y_recepcion(): void
    {
        $admin = User::query()->where('email', 'admin@sigcm.local')->firstOrFail();
        $medico = User::query()->where('email', 'medico@sigcm.local')->firstOrFail();
        $recepcion = User::query()->where('email', 'recepcion@sigcm.local')->firstOrFail();

        $connection = DB::connection('pgsql');
        $connection->beginTransaction();

        try {
            $draftConsultation = MedicalConsultation::query()->where('status', 'draft')->first()
                ?? MedicalConsultation::factory()->create();
            $signedConsultation = MedicalConsultation::query()->where('status', 'signed')->firstOrFail();
            $auditSample = AuditEvent::query()->firstOrFail();
            $draftPrescription = Prescription::query()->where('status', 'draft')->first()
                ?? Prescription::factory()->create(['status' => 'draft']);

            // Recepción: agenda y pacientes sí; consultas médicas, recetas y auditoría global no
            $this->assertTrue($recepcion->can('viewAny', Patient::class));
            $this->assertTrue($recepcion->can('create', Appointment::class));
            $this->assertFalse($recepcion->can('create', MedicalConsultation::class));
            $this->assertFalse($recepcion->can('create', Prescription::class));
            $this->assertFalse($recepcion->can('viewAny', AuditEvent::class));

            // Médico: atiende consultas y prescribe en borrador; no altera firmadas ni gestiona auditoría global
            $this->assertTrue($medico->can('create', MedicalConsultation::class));
            $this->assertTrue($medico->can('update', $draftConsultation));
            $this->assertFalse($medico->can('update', $signedConsultation));
            $this->assertFalse($medico->can('delete', $signedConsultation));
            $this->assertTrue($medico->can('update', $draftPrescription));
            $this->assertFalse($medico->can('viewAny', AuditEvent::class));

            // Super Admin: acceso administrativo incluyendo auditoría solo lectura (nunca crear/editar/borrar bitácora)
            $this->assertTrue($admin->can('viewAny', AuditEvent::class));
            $this->assertTrue($admin->can('view', $auditSample));
            $this->assertTrue($admin->can('viewAny', Patient::class));
        } finally {
            $connection->rollBack();
        }
    }
}
