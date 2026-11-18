<?php

namespace Tests\Feature;

use App\Filament\Resources\Patients\Pages\ListPatients;
use App\Filament\Resources\Patients\Pages\ViewPatient;
use App\Filament\Resources\Patients\RelationManagers\AllergiesRelationManager;
use App\Filament\Resources\Patients\RelationManagers\AppointmentsRelationManager;
use App\Filament\Resources\Patients\RelationManagers\ConditionsRelationManager;
use App\Filament\Resources\Patients\RelationManagers\DocumentsRelationManager;
use App\Filament\Resources\Patients\RelationManagers\MedicalConsultationsRelationManager;
use App\Filament\Resources\Patients\RelationManagers\MedicationsRelationManager;
use App\Models\AuditEvent;
use App\Models\Patient;
use App\Models\PatientAllergy;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class SigcmF1PatientProfileTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['database.default' => 'pgsql']);
        DB::purge('pgsql');
    }

    public function test_listado_de_pacientes_filtra_busca_y_expone_exportacion_excel(): void
    {
        $medico = User::query()->where('email', 'medico@sigcm.local')->firstOrFail();
        $patient = Patient::query()->orderBy('id')->firstOrFail();

        $this->actingAs($medico);

        Livewire::test(ListPatients::class)
            ->assertOk()
            ->assertTableActionExists('exportarExcel', null)
            ->searchTable($patient->document_number)
            ->assertCanSeeTableRecords([$patient])
            ->filterTable('is_active', true)
            ->assertOk();
    }

    public function test_perfil_longitudinal_view_patient_muestra_tarjetas_alertas_y_todos_los_tabs(): void
    {
        $medico = User::query()->where('email', 'medico@sigcm.local')->firstOrFail();
        $patient = Patient::query()->with(['clinicalRecord', 'allergies', 'conditions'])->orderBy('id')->firstOrFail();

        $this->actingAs($medico)
            ->get("/admin/patients/{$patient->id}")
            ->assertOk()
            ->assertSee($patient->full_name)
            ->assertSee(__('sigcm.patients.cards.patient_data'))
            ->assertSee(__('sigcm.patients.cards.clinical_alerts'))
            ->assertSee(__('sigcm.patients.summary_tab'));

        Livewire::test(ViewPatient::class, ['record' => $patient->getRouteKey()])
            ->assertOk()
            ->assertSee(__('sigcm.patients.consultations_tab'))
            ->assertSee(__('sigcm.patients.appointments_tab'))
            ->assertSee(__('sigcm.patients.allergies_tab'))
            ->assertSee(__('sigcm.patients.conditions_tab'))
            ->assertSee(__('sigcm.patients.medications_tab'))
            ->assertSee(__('sigcm.patients.documents_tab'));

        $managers = [
            MedicalConsultationsRelationManager::class,
            AppointmentsRelationManager::class,
            AllergiesRelationManager::class,
            ConditionsRelationManager::class,
            MedicationsRelationManager::class,
            DocumentsRelationManager::class,
        ];

        foreach ($managers as $manager) {
            Livewire::test($manager, [
                'ownerRecord' => $patient,
                'pageClass' => ViewPatient::class,
            ])->assertOk();
        }
    }

    public function test_registrar_alergia_desde_ficha_del_paciente_audita_el_actor(): void
    {
        $medico = User::query()->where('email', 'medico@sigcm.local')->firstOrFail();
        $patient = Patient::query()->orderBy('id')->firstOrFail();
        $connection = DB::connection('pgsql');
        $connection->beginTransaction();

        try {
            $this->actingAs($medico);

            Livewire::test(AllergiesRelationManager::class, [
                'ownerRecord' => $patient,
                'pageClass' => ViewPatient::class,
            ])
                ->callTableAction('create', data: [
                    'substance' => 'Penicilina G Sódica Prueba',
                    'reaction' => 'Exantema cutáneo',
                    'severity' => 'severe',
                    'verification_status' => 'confirmed',
                    'clinical_status' => 'active',
                ])
                ->assertHasNoTableActionErrors();

            $createdAllergy = PatientAllergy::query()
                ->where('patient_id', $patient->id)
                ->where('substance', 'Penicilina G Sódica Prueba')
                ->first();

            $this->assertNotNull($createdAllergy);

            $auditEvent = AuditEvent::query()
                ->where('entity_name', 'patient_allergies')
                ->where('entity_id', $createdAllergy->id)
                ->where('operation', 'INSERT')
                ->first();

            $this->assertNotNull($auditEvent);
            $this->assertSame($medico->id, $auditEvent->actor_user_id);
        } finally {
            $connection->rollBack();
        }
    }
}
