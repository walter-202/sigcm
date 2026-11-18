<?php

namespace Tests\Feature;

use App\Enums\AppointmentStatus;
use App\Enums\ConsultationStatus;
use App\Enums\EpisodeStatus;
use App\Filament\Resources\ClinicalRecords\ClinicalRecordResource;
use App\Filament\Resources\MedicalConsultations\MedicalConsultationResource;
use App\Models\AuditEvent;
use App\Models\ClinicalRecord;
use App\Models\Doctor;
use App\Models\MedicalConsultation;
use App\Models\Patient;
use App\Models\User;
use App\Services\SigcmDatabaseService;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SigcmFilamentResourcesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['database.default' => 'pgsql']);
        DB::purge('pgsql');
    }

    public function test_enums_de_dominio_exponen_etiquetas_en_espanol_y_colores(): void
    {
        $this->assertSame('Pendiente', AppointmentStatus::Pending->getLabel());
        $this->assertSame('warning', AppointmentStatus::Pending->getColor());
        $this->assertSame('Completada', AppointmentStatus::Completed->getLabel());
        $this->assertSame('success', AppointmentStatus::Completed->getColor());

        $this->assertSame('Borrador', ConsultationStatus::Draft->getLabel());
        $this->assertSame('Firmada', ConsultationStatus::Signed->getLabel());

        $this->assertSame('Activo', EpisodeStatus::Active->getLabel());
        $this->assertSame('Resuelto', EpisodeStatus::Resolved->getLabel());
    }

    public function test_rutas_de_listado_y_vista_de_los_cinco_recursos_responden_en_espanol(): void
    {
        $admin = User::query()->where('email', 'admin@sigcm.local')->first();

        $this->assertNotNull($admin);

        $this->actingAs($admin)->get('/admin/patients')->assertOk()->assertSee('Pacientes');
        $this->actingAs($admin)->get('/admin/patients/1')->assertOk()->assertSee(__('sigcm.patients.cards.patient_data'));

        $this->actingAs($admin)->get('/admin/doctors')->assertOk()->assertSee('Médicos');
        $this->actingAs($admin)->get('/admin/doctors/1')->assertOk()->assertSee('Perfil del profesional');

        $this->actingAs($admin)->get('/admin/appointments')->assertOk()->assertSee('Citas Médicas');
        $this->actingAs($admin)->get('/admin/appointments/1')->assertOk()->assertSee('Detalle de la cita');

        $this->actingAs($admin)->get('/admin/medical-consultations')->assertOk()->assertSee('Consultas Médicas');
        $this->actingAs($admin)->get('/admin/medical-consultations/1')->assertOk()->assertSee(__('sigcm.consultation.sections.clinical_note'));

        $this->actingAs($admin)->get('/admin/clinical-records')->assertOk()->assertSee('Historias Clínicas');
        $this->actingAs($admin)->get('/admin/clinical-records/1')->assertOk()->assertSee('Expediente clínico');
    }

    public function test_reglas_de_inmutabilidad_y_apertura_de_historia_en_recursos(): void
    {
        $admin = User::query()->where('email', 'admin@sigcm.local')->first();
        $this->assertNotNull($admin);
        $this->actingAs($admin);

        $this->assertFalse(ClinicalRecordResource::canCreate());

        $signed = MedicalConsultation::query()
            ->where('status', ConsultationStatus::Signed->value)
            ->first();
        $this->assertNotNull($signed);
        $this->assertFalse(MedicalConsultationResource::canEdit($signed));
        $this->assertFalse(MedicalConsultationResource::canDelete($signed));

        $connection = DB::connection('pgsql');
        $connection->beginTransaction();

        try {
            $draft = MedicalConsultation::factory()->create([
                'patient_id' => 1,
                'doctor_id' => 1,
            ]);

            $this->assertTrue(MedicalConsultationResource::canEdit($draft));
        } finally {
            $connection->rollBack();
        }
    }

    public function test_firmar_consulta_existente_en_borrador_abre_expediente_y_registra_auditoria(): void
    {
        $admin = User::query()->where('email', 'admin@sigcm.local')->first();
        $this->assertNotNull($admin);

        $connection = DB::connection('pgsql');
        $connection->beginTransaction();

        try {
            $patient = Patient::factory()->create();
            $doctor = Doctor::factory()->create();

            $draft = MedicalConsultation::factory()->create([
                'patient_id' => $patient->id,
                'doctor_id' => $doctor->id,
                'chief_complaint' => 'Cefalea tensional de tres días de evolución',
            ]);

            app(SigcmDatabaseService::class)->firmarConsultaExistente(
                consultation: $draft,
                actorUserId: (int) $admin->id,
                episodeTitle: 'Evaluación neurológica inicial',
            );

            $draft->refresh();
            $record = ClinicalRecord::query()->where('patient_id', $patient->id)->first();
            $signAudit = AuditEvent::query()
                ->where('entity_name', 'medical_consultations')
                ->where('entity_id', $draft->id)
                ->where('operation', 'SIGN')
                ->first();

            $this->assertSame(ConsultationStatus::Signed, $draft->status);
            $this->assertNotNull($draft->signed_at);
            $this->assertNotNull($record);
            $this->assertSame($draft->id, $record->opened_from_consultation_id);
            $this->assertCount(1, $record->careEpisodes);
            $this->assertNotNull($signAudit);
        } finally {
            $connection->rollBack();
        }
    }
}
