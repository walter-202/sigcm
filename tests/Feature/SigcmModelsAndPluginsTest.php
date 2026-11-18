<?php

namespace Tests\Feature;

use App\Models\CareEpisode;
use App\Models\CarePlan;
use App\Models\CarePlanActivity;
use App\Models\ClinicalDocument;
use App\Models\ClinicalObservation;
use App\Models\ClinicalOrder;
use App\Models\ConsultationDiagnosis;
use App\Models\DiagnosticReport;
use App\Models\DiagnosticResultItem;
use App\Models\MedicalConsultation;
use App\Models\Patient;
use App\Models\PatientAllergy;
use App\Models\PatientCondition;
use App\Models\PatientMedication;
use App\Models\Prescription;
use App\Models\PrescriptionItem;
use App\Models\Referral;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;
use pxlrbt\FilamentExcel\Actions\ExportAction;
use Tests\TestCase;

class SigcmModelsAndPluginsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['database.default' => 'pgsql']);
        DB::purge('pgsql');
    }

    public function test_panel_admin_registra_los_plugins_aprobados_de_filament_5(): void
    {
        $panel = Filament::getPanel('admin');

        $this->assertTrue($panel->hasPlugin('filament-shield'));
        $this->assertTrue($panel->hasPlugin('guava-calendar'));
        $this->assertTrue($panel->hasPlugin('filament-apex-charts'));
        $this->assertTrue($panel->hasPlugin('pxlrbt/filament-spotlight'));
        $this->assertTrue(class_exists(ExportAction::class));
    }

    public function test_los_modelos_eloquent_cubren_las_tablas_clinicas_y_sus_factories_funcionan_en_postgresql(): void
    {
        $connection = DB::connection('pgsql');
        $connection->beginTransaction();

        try {
            $seededModels = [
                PatientCondition::class,
                PatientAllergy::class,
                PatientMedication::class,
                ConsultationDiagnosis::class,
                ClinicalObservation::class,
                Prescription::class,
                PrescriptionItem::class,
                ClinicalOrder::class,
                DiagnosticReport::class,
                DiagnosticResultItem::class,
                Referral::class,
                ClinicalDocument::class,
                CarePlan::class,
                CarePlanActivity::class,
            ];

            foreach ($seededModels as $modelClass) {
                $this->assertGreaterThanOrEqual(
                    10,
                    $modelClass::query()->count(),
                    "El modelo {$modelClass} debe leer los registros iniciales sembrados."
                );
            }

            $patient = Patient::factory()->create();
            $condition = PatientCondition::factory()->create(['patient_id' => $patient->id]);
            $allergy = PatientAllergy::factory()->create(['patient_id' => $patient->id]);
            $medication = PatientMedication::factory()->create(['patient_id' => $patient->id]);

            $consultation = MedicalConsultation::factory()->create(['patient_id' => $patient->id]);
            $diagnosis = ConsultationDiagnosis::factory()->create([
                'consultation_id' => $consultation->id,
                'patient_condition_id' => $condition->id,
            ]);
            $observation = ClinicalObservation::factory()->create(['consultation_id' => $consultation->id]);

            $prescription = Prescription::factory()->create(['consultation_id' => $consultation->id]);
            $prescriptionItem = PrescriptionItem::factory()->create(['prescription_id' => $prescription->id]);

            $order = ClinicalOrder::factory()->create(['consultation_id' => $consultation->id]);
            $report = DiagnosticReport::factory()->create(['clinical_order_id' => $order->id]);
            $resultItem = DiagnosticResultItem::factory()->create(['diagnostic_report_id' => $report->id]);

            $referral = Referral::factory()->create(['consultation_id' => $consultation->id]);
            $document = ClinicalDocument::factory()->create([
                'patient_id' => $patient->id,
                'consultation_id' => $consultation->id,
            ]);

            $carePlan = CarePlan::factory()->create();
            $careActivity = CarePlanActivity::factory()->create(['care_plan_id' => $carePlan->id]);

            $episode = $carePlan->episode;
            $episodeConditionPatient = $episode->clinicalRecord->patient_id;
            $matchedCondition = PatientCondition::factory()->create(['patient_id' => $episodeConditionPatient]);
            $episode->conditions()->attach($matchedCondition->id, ['created_at' => now()]);

            $this->assertCount(1, $patient->conditions);
            $this->assertCount(1, $patient->allergies);
            $this->assertCount(1, $patient->medications);
            $this->assertCount(1, $patient->documents);
            $this->assertSame($allergy->id, $patient->allergies->first()?->id);
            $this->assertSame($medication->id, $patient->medications->first()?->id);
            $this->assertSame($diagnosis->id, $consultation->diagnoses->first()?->id);
            $this->assertSame($observation->id, $consultation->observations->first()?->id);
            $this->assertSame($prescriptionItem->id, $prescription->items->first()?->id);
            $this->assertSame($resultItem->id, $report->resultItems->first()?->id);
            $this->assertSame($referral->id, $consultation->referrals->first()?->id);
            $this->assertSame($document->id, $consultation->documents->first()?->id);
            $this->assertSame($careActivity->id, $carePlan->activities->first()?->id);
            $this->assertInstanceOf(CareEpisode::class, $episode);
            $this->assertCount(1, $episode->conditions);
        } finally {
            $connection->rollBack();
        }
    }
}
