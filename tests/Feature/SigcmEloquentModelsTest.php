<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\AppointmentReschedule;
use App\Models\AuditEvent;
use App\Models\CareEpisode;
use App\Models\ClinicalRecord;
use App\Models\ConsultationAmendment;
use App\Models\Doctor;
use App\Models\FollowUpPlan;
use App\Models\MedicalConsultation;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SigcmEloquentModelsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['database.default' => 'pgsql']);
        DB::purge('pgsql');
    }

    public function test_modelos_eloquent_leen_datos_semilla_y_resuelven_relaciones(): void
    {
        $patient = Patient::query()->with([
            'clinicalRecord.careEpisodes.consultations',
            'appointments',
            'medicalConsultations.amendments',
        ])->find(1);

        $this->assertNotNull($patient);
        $this->assertNotEmpty($patient->full_name);
        $this->assertNotNull($patient->clinicalRecord);
        $this->assertNotEmpty($patient->clinicalRecord->careEpisodes);
        $this->assertNotEmpty($patient->clinicalRecord->careEpisodes->first()->consultations);
        $this->assertNotEmpty($patient->appointments);
        $this->assertNotEmpty($patient->medicalConsultations->first()->amendments);

        $doctor = Doctor::query()->with(['user', 'appointments', 'medicalConsultations'])->find(1);
        $this->assertNotNull($doctor);
        $this->assertNotNull($doctor->user);
        $this->assertNotNull($doctor->user->doctor);
        $this->assertNotEmpty($doctor->appointments);
        $this->assertNotEmpty($doctor->medicalConsultations);

        $appointment = Appointment::query()->with(['patient', 'doctor', 'createdByUser', 'reschedules', 'medicalConsultation'])->find(1);
        $this->assertNotNull($appointment);
        $this->assertNotNull($appointment->patient);
        $this->assertNotNull($appointment->doctor);
        $this->assertNotNull($appointment->createdByUser);
        $this->assertNotEmpty($appointment->reschedules);
        $this->assertNotNull($appointment->medicalConsultation);

        $reschedule = AppointmentReschedule::query()->with(['appointment', 'changedByUser'])->find(1);
        $this->assertNotNull($reschedule);
        $this->assertNotNull($reschedule->appointment);
        $this->assertNotNull($reschedule->changedByUser);

        $followUp = FollowUpPlan::query()->with(['originatingConsultation', 'episode'])->find(1);
        $this->assertNotNull($followUp);
        $this->assertNotNull($followUp->originatingConsultation);
        $this->assertNotNull($followUp->episode);

        $auditEvent = AuditEvent::query()->with('actor')->orderBy('id')->first();
        $this->assertNotNull($auditEvent);
        $this->assertIsArray($auditEvent->changed_fields);
        $this->assertInstanceOf(User::class, $auditEvent->actor);
    }

    public function test_factories_crean_entidades_clinicas_y_disparan_auditoria_en_transaccion(): void
    {
        $connection = DB::connection('pgsql');
        $connection->beginTransaction();

        try {
            $doctor = Doctor::factory()->withUser()->create();
            $patient = Patient::factory()->create();
            $appointment = Appointment::factory()->create([
                'patient_id' => $patient->id,
                'doctor_id' => $doctor->id,
                'created_by_user_id' => $doctor->user_id,
            ]);

            $consultation = MedicalConsultation::factory()
                ->signed()
                ->forAppointment($appointment)
                ->create();

            $record = ClinicalRecord::factory()->create([
                'patient_id' => $patient->id,
                'opened_from_consultation_id' => $consultation->id,
            ]);

            $episode = CareEpisode::factory()->create([
                'clinical_record_id' => $record->id,
                'opening_consultation_id' => $consultation->id,
            ]);

            $followUp = FollowUpPlan::factory()->create([
                'originating_consultation_id' => $consultation->id,
                'episode_id' => $episode->id,
                'appointment_id' => $appointment->id,
            ]);

            $amendment = ConsultationAmendment::factory()->create([
                'consultation_id' => $consultation->id,
                'author_user_id' => $doctor->user_id,
            ]);

            $amendAudit = AuditEvent::query()
                ->where('entity_name', 'consultation_amendments')
                ->where('entity_id', $amendment->id)
                ->first();

            $this->assertSame($patient->id, $record->patient_id);
            $this->assertSame($consultation->id, $followUp->originating_consultation_id);
            $this->assertNotNull($amendAudit);
            $this->assertSame('AMEND', $amendAudit->operation);
        } finally {
            $connection->rollBack();
        }
    }
}
