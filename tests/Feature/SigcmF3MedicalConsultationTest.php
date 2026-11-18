<?php

namespace Tests\Feature;

use App\Enums\ConsultationStatus;
use App\Filament\Resources\MedicalConsultations\Pages\CreateMedicalConsultation;
use App\Filament\Resources\MedicalConsultations\Pages\ViewMedicalConsultation;
use App\Filament\Resources\MedicalConsultations\RelationManagers\AmendmentsRelationManager;
use App\Models\AuditEvent;
use App\Models\ClinicalObservation;
use App\Models\ConsultationAmendment;
use App\Models\ConsultationDiagnosis;
use App\Models\MedicalConsultation;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class SigcmF3MedicalConsultationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['database.default' => 'pgsql']);
        DB::purge('pgsql');
    }

    public function test_consulta_medica_muestra_secciones_clinicas_reales_sin_jerga_soap(): void
    {
        $medico = User::query()->where('email', 'medico@sigcm.local')->firstOrFail();

        $this->actingAs($medico)
            ->get('/admin/medical-consultations/create?patient_id=1')
            ->assertOk()
            ->assertSee(__('sigcm.consultation.sections.clinical_note'))
            ->assertSee(__('sigcm.consultation.sections.vitals'))
            ->assertSee(__('sigcm.consultation.sections.diagnoses'))
            ->assertDontSee('Nota clínica SOAP')
            ->assertDontSee('Subjetivo (S)');
    }

    public function test_flujo_completo_de_borrador_signos_diagnosticos_firma_y_enmienda(): void
    {
        $medico = User::query()->where('email', 'medico@sigcm.local')->firstOrFail();
        $patient = Patient::query()->orderBy('id')->firstOrFail();
        $connection = DB::connection('pgsql');
        $connection->beginTransaction();

        try {
            $this->actingAs($medico);

            Livewire::test(CreateMedicalConsultation::class)
                ->fillForm([
                    'patient_id' => $patient->id,
                    'doctor_id' => 1,
                    'occurred_at' => now()->subMinutes(5)->format('Y-m-d H:i:s'),
                    'chief_complaint' => 'Cefalea tensional y control de cifras tensionales',
                    'subjective' => 'Refiere cefalea frontal leve desde hace 48 horas.',
                    'objective' => 'Paciente vigil, orientado, cardiopulmonar sin ruidos agregados.',
                    'assessment' => 'Hipertensión esencial controlada; cefalea tensional.',
                    'plan' => 'Continuar terapia habitual y control ambulatorio en 14 días.',
                    'observations' => [
                        'vital-1' => [
                            'code' => 'blood_pressure',
                            'unit_code' => 'mmHg',
                            'value_text' => '126/82',
                            'observed_at' => now()->subMinutes(4)->format('Y-m-d H:i:s'),
                        ],
                        'vital-2' => [
                            'code' => 'heart_rate',
                            'unit_code' => 'lpm',
                            'value_number' => 74,
                            'observed_at' => now()->subMinutes(4)->format('Y-m-d H:i:s'),
                        ],
                    ],
                    'diagnoses' => [
                        'dx-1' => [
                            'code_system' => 'CIE-10',
                            'code' => 'I10',
                            'diagnosis_text' => 'Hipertensión arterial esencial',
                            'certainty' => 'confirmed',
                            'is_primary' => true,
                        ],
                    ],
                ])
                ->call('create')
                ->assertHasNoFormErrors();

            $consultation = MedicalConsultation::query()
                ->where('chief_complaint', 'Cefalea tensional y control de cifras tensionales')
                ->first();

            $this->assertNotNull($consultation);
            $this->assertSame(ConsultationStatus::Draft, $consultation->status);
            $this->assertSame(2, ClinicalObservation::query()->where('consultation_id', $consultation->id)->count());
            $this->assertSame(1, ConsultationDiagnosis::query()->where('consultation_id', $consultation->id)->count());

            Livewire::test(ViewMedicalConsultation::class, ['record' => $consultation->getRouteKey()])
                ->callAction('firmarConsulta', data: [
                    'episode_title' => 'Control ambulatorio cefalea e HTA',
                ])
                ->assertNotified('Consulta firmada y expediente actualizado');

            $consultation->refresh();
            $this->assertSame(ConsultationStatus::Signed, $consultation->status);
            $this->assertNotNull($consultation->signed_at);

            $signAudit = AuditEvent::query()
                ->where('entity_name', 'medical_consultations')
                ->where('entity_id', $consultation->id)
                ->where('operation', 'SIGN')
                ->first();

            $this->assertNotNull($signAudit);
            $this->assertSame($medico->id, $signAudit->actor_user_id);

            Livewire::test(AmendmentsRelationManager::class, [
                'ownerRecord' => $consultation,
                'pageClass' => ViewMedicalConsultation::class,
            ])
                ->callTableAction('create', data: [
                    'reason' => 'Precisión terapéutica adicional',
                    'additional_note' => 'Se indica hidratación oral y monitoreo domiciliario de PA.',
                ])
                ->assertHasNoTableActionErrors();

            $this->assertDatabaseHas(ConsultationAmendment::class, [
                'consultation_id' => $consultation->id,
                'author_user_id' => $medico->id,
                'reason' => 'Precisión terapéutica adicional',
            ]);
        } finally {
            $connection->rollBack();
        }
    }
}
