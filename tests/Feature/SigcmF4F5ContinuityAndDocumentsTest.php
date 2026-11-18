<?php

namespace Tests\Feature;

use App\Filament\Resources\ClinicalRecords\Pages\ViewClinicalRecord;
use App\Filament\Resources\ClinicalRecords\RelationManagers\CareEpisodesRelationManager;
use App\Filament\Resources\MedicalConsultations\Pages\ViewMedicalConsultation;
use App\Filament\Resources\MedicalConsultations\RelationManagers\ClinicalOrdersRelationManager;
use App\Filament\Resources\MedicalConsultations\RelationManagers\OriginatedFollowUpPlansRelationManager;
use App\Filament\Resources\MedicalConsultations\RelationManagers\PrescriptionsRelationManager;
use App\Filament\Resources\MedicalConsultations\RelationManagers\ReferralsRelationManager;
use App\Models\CareEpisode;
use App\Models\CarePlan;
use App\Models\ClinicalOrder;
use App\Models\ClinicalRecord;
use App\Models\FollowUpPlan;
use App\Models\MedicalConsultation;
use App\Models\Prescription;
use App\Models\Referral;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class SigcmF4F5ContinuityAndDocumentsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['database.default' => 'pgsql']);
        DB::purge('pgsql');
    }

    public function test_recetas_emision_sustitucion_y_vistas_blade_de_impresion(): void
    {
        $medico = User::query()->where('email', 'medico@sigcm.local')->firstOrFail();
        $recepcion = User::query()->where('email', 'recepcion@sigcm.local')->firstOrFail();
        $consultation = MedicalConsultation::query()->orderBy('id')->firstOrFail();
        $connection = DB::connection('pgsql');
        $connection->beginTransaction();

        try {
            $this->actingAs($medico);

            $draftPrescription = Prescription::factory()->create([
                'consultation_id' => $consultation->id,
                'status' => 'draft',
                'notes' => 'Receta inicial de prueba',
            ]);

            $draftPrescription->items()->create([
                'medication_name' => 'Losartán 50 mg',
                'dose' => '1 comprimido',
                'route' => 'Vía oral',
                'frequency' => 'Cada 12 horas',
                'duration_days' => 30,
                'quantity' => 60,
                'created_at' => now(),
            ]);

            $manager = Livewire::test(PrescriptionsRelationManager::class, [
                'ownerRecord' => $consultation,
                'pageClass' => ViewMedicalConsultation::class,
            ]);

            $manager->callTableAction('emitir', $draftPrescription)
                ->assertNotified('Receta emitida');

            $draftPrescription->refresh();
            $this->assertSame('issued', $draftPrescription->status);

            $manager->callTableAction('sustituir', $draftPrescription, data: [
                'notes' => 'Cambio de esquema por tos seca',
            ])->assertHasNoTableActionErrors();

            $draftPrescription->refresh();
            $this->assertSame('replaced', $draftPrescription->status);

            $replacement = Prescription::query()
                ->where('supersedes_prescription_id', $draftPrescription->id)
                ->first();
            $this->assertNotNull($replacement);
            $this->assertSame('draft', $replacement->status);

            // Impresión Blade autorizada para médico; denegada para recepción
            $this->get(route('sigcm.print.consultation', $consultation))
                ->assertOk()
                ->assertSee('Resumen de Atención Ambulatoria');

            $this->get(route('sigcm.print.prescription', $draftPrescription))
                ->assertOk()
                ->assertSee('Losartán 50 mg');

            $this->actingAs($recepcion)
                ->get(route('sigcm.print.prescription', $draftPrescription))
                ->assertForbidden();
        } finally {
            $connection->rollBack();
        }
    }

    public function test_ordenes_informes_interconsultas_seguimientos_y_planes_de_cuidado(): void
    {
        $medico = User::query()->where('email', 'medico@sigcm.local')->firstOrFail();
        $consultation = MedicalConsultation::query()->where('status', 'signed')->orderBy('id')->firstOrFail();
        $clinicalRecord = ClinicalRecord::query()->where('patient_id', $consultation->patient_id)->firstOrFail();
        $episode = CareEpisode::query()->where('clinical_record_id', $clinicalRecord->id)->firstOrFail();

        $connection = DB::connection('pgsql');
        $connection->beginTransaction();

        try {
            $this->actingAs($medico);

            // Órdenes diagnósticas -> informe -> revisión médica
            $order = ClinicalOrder::factory()->create([
                'consultation_id' => $consultation->id,
                'title' => 'Perfil lipídico completo de control',
            ]);

            $ordersManager = Livewire::test(ClinicalOrdersRelationManager::class, [
                'ownerRecord' => $consultation,
                'pageClass' => ViewMedicalConsultation::class,
            ]);

            $ordersManager->mountTableAction('registrarInforme', $order)
                ->set('mountedActions.0.data', [
                    'interpretation' => 'Colesterol total ligeramente elevado.',
                    'items' => [
                        'item-1' => [
                            'test_code' => 'COL',
                            'test_name' => 'Colesterol total',
                            'value_kind' => 'number',
                            'result_number' => 212.5,
                            'unit_code' => 'mg/dL',
                            'reference_range' => '< 200 mg/dL',
                        ],
                    ],
                ])
                ->callMountedTableAction()
                ->assertHasNoTableActionErrors()
                ->assertNotified('Informe diagnóstico registrado');

            $ordersManager->callTableAction('marcarRevisado', $order->fresh(), data: [
                'interpretation' => 'Revisado en consulta, reforzar dieta.',
            ])->assertNotified('Informe marcado como revisado');

            $this->assertSame('reviewed', $order->diagnosticReports()->latest('id')->first()?->status);

            // Interconsulta
            Livewire::test(ReferralsRelationManager::class, [
                'ownerRecord' => $consultation,
                'pageClass' => ViewMedicalConsultation::class,
            ])->callTableAction('create', data: [
                'specialty_or_service' => 'Nutrición clínica',
                'status' => 'requested',
                'reason' => 'Plan nutricional hipolipemiante',
            ])->assertHasNoTableActionErrors();

            $this->assertDatabaseHas(Referral::class, [
                'consultation_id' => $consultation->id,
                'specialty_or_service' => 'Nutrición clínica',
            ]);

            // Seguimiento -> cita de control programada
            $followUp = FollowUpPlan::query()->create([
                'originating_consultation_id' => $consultation->id,
                'episode_id' => $episode->id,
                'due_at' => '2026-12-28 10:00:00-04',
                'goal' => 'Reevaluar perfil lipídico en 4 semanas',
                'status' => 'pending',
            ]);

            Livewire::test(OriginatedFollowUpPlansRelationManager::class, [
                'ownerRecord' => $consultation,
                'pageClass' => ViewMedicalConsultation::class,
            ])->callTableAction('programarCitaControl', $followUp, data: [
                'doctor_id' => $consultation->doctor_id,
                'starts_at' => '2026-12-28 10:00:00',
                'ends_at' => '2026-12-28 10:30:00',
            ])->assertNotified('Cita de control programada');

            $followUp->refresh();
            $this->assertSame('scheduled', $followUp->status);
            $this->assertNotNull($followUp->appointment_id);

            // Plan de cuidados desde episodio en Historia Clínica
            Livewire::test(CareEpisodesRelationManager::class, [
                'ownerRecord' => $clinicalRecord,
                'pageClass' => ViewClinicalRecord::class,
            ])->mountTableAction('crearPlanCuidados', $episode)
                ->set('mountedActions.0.data', [
                    'created_from_consultation_id' => $consultation->id,
                    'objective' => 'Control lipídico y actividad física aeróbica',
                    'activities' => [
                        'act-1' => [
                            'description' => 'Caminata diaria de 30 minutos',
                            'status' => 'pending',
                            'due_at' => '2026-12-28 10:00:00',
                        ],
                    ],
                ])
                ->callMountedTableAction()
                ->assertHasNoTableActionErrors()
                ->assertNotified('Plan de cuidados registrado');

            $this->assertDatabaseHas(CarePlan::class, [
                'episode_id' => $episode->id,
                'objective' => 'Control lipídico y actividad física aeróbica',
            ]);
        } finally {
            $connection->rollBack();
        }
    }
}
