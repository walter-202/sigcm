<?php

namespace Tests\Feature;

use App\Enums\AppointmentStatus;
use App\Enums\ConsultationStatus;
use App\Filament\Resources\Appointments\Pages\ViewAppointment;
use App\Filament\Resources\ClinicalRecords\Pages\ViewClinicalRecord;
use App\Filament\Resources\ClinicalRecords\RelationManagers\ClinicalFollowUpPlansRelationManager;
use App\Filament\Resources\MedicalConsultations\MedicalConsultationResource;
use App\Filament\Resources\MedicalConsultations\Pages\CreateMedicalConsultation;
use App\Filament\Resources\MedicalConsultations\Pages\ViewMedicalConsultation;
use App\Filament\Resources\Patients\Pages\CreatePatient;
use App\Filament\Resources\Patients\Pages\ViewPatient;
use App\Filament\Resources\Patients\RelationManagers\PatientClinicalOrdersRelationManager;
use App\Filament\Resources\Patients\RelationManagers\PatientPrescriptionsRelationManager;
use App\Filament\Resources\Patients\RelationManagers\PatientReferralsRelationManager;
use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Filament\Widgets\AgendaCalendarWidget;
use App\Models\Appointment;
use App\Models\AuditEvent;
use App\Models\CareEpisode;
use App\Models\ClinicalRecord;
use App\Models\FollowUpPlan;
use App\Models\MedicalConsultation;
use App\Models\Patient;
use App\Models\PatientCondition;
use App\Models\User;
use App\Services\SigcmDatabaseService;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SigcmBacklogValidationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['database.default' => 'pgsql']);
        DB::purge('pgsql');
    }

    public function test_sig01_y_sig02_patient_form_full_width_edad_en_vivo_y_spotlight_espanol(): void
    {
        $medico = User::query()->where('email', 'medico@sigcm.local')->firstOrFail();
        $connection = DB::connection('pgsql');
        $connection->beginTransaction();

        try {
            $this->actingAs($medico);

            $this->assertSame('Buscar :record', __('filament-spotlight::spotlight.placeholder'));
            $this->assertSame('¿Qué acción deseas realizar?', __('livewire-ui-spotlight::spotlight.placeholder'));

            $this->get('/admin/patients/create')
                ->assertOk()
                ->assertSee(__('sigcm.patients.form.identification_section'))
                ->assertSee(__('sigcm.patients.form.status_section'))
                ->assertDontSee('List Patients')
                ->assertDontSee('Create Patient');

            // SIG-02: al cambiar a locale 'en' (sin fallback a 'es'), deben verse claves sigcm.* y no español quemado
            $previousFallback = app('translator')->getFallback();
            config(['app.fallback_locale' => 'en']);
            app('translator')->setFallback('en');
            app()->setLocale('en');
            try {
                $this->get('/admin/patients/create')
                    ->assertOk()
                    ->assertSee('sigcm.patients.form.identification_section')
                    ->assertSee('sigcm.fields.first_names')
                    ->assertDontSee('Identificación del paciente');
            } finally {
                config(['app.fallback_locale' => $previousFallback]);
                app('translator')->setFallback($previousFallback);
                app()->setLocale('es');
            }

            Livewire::test(CreatePatient::class)
                ->fillForm([
                    'first_names' => 'Lucía Esperanza',
                    'last_names' => 'Vargas Rojas',
                    'document_type' => 'CI',
                    'document_number' => '99887766',
                    'birth_date' => now()->subYears(32)->toDateString(),
                    'phone' => '71234567',
                    'email' => 'lucia.vargas@ejemplo.com',
                    'is_active' => true,
                ])
                ->assertSee('Edad estimada actual: 32 años.')
                ->call('create')
                ->assertHasNoFormErrors();

            $this->assertDatabaseHas(Patient::class, [
                'document_number' => '99887766',
                'is_active' => true,
            ]);
        } finally {
            $connection->rollBack();
        }
    }

    public function test_sig03_sig04_sig06_y_sig14_tabs_longitudinales_episodios_y_auditoria_contextual(): void
    {
        $medico = User::query()->where('email', 'medico@sigcm.local')->firstOrFail();
        $recepcion = User::query()->where('email', 'recepcion@sigcm.local')->firstOrFail();
        $patient = Patient::query()->with('clinicalRecord')->orderBy('id')->firstOrFail();
        $episode = CareEpisode::query()->where('clinical_record_id', $patient->clinicalRecord?->id)->firstOrFail();
        $condition = PatientCondition::query()->where('patient_id', $patient->id)->firstOrFail();
        $signedConsultation = MedicalConsultation::query()
            ->where('patient_id', $patient->id)
            ->where('status', ConsultationStatus::Signed->value)
            ->firstOrFail();

        $connection = DB::connection('pgsql');
        $connection->beginTransaction();

        try {
            $episode->conditions()->syncWithoutDetaching([$condition->id => ['created_at' => now()]]);

            $this->actingAs($medico);

            $patientPage = Livewire::test(ViewPatient::class, ['record' => $patient->getRouteKey()])
                ->assertOk()
                ->assertSee(__('sigcm.patients.clinical_orders_tab'))
                ->assertSee($condition->display)
                ->assertActionVisible('trazabilidadAuditada')
                ->mountAction('trazabilidadAuditada')
                ->assertActionMounted('trazabilidadAuditada');

            $patientAuditModalHtml = (string) $patientPage->instance()->getMountedAction()?->getModalContent()?->render();
            $this->assertStringContainsString('SIGN', $patientAuditModalHtml);
            $this->assertStringContainsString((string) $signedConsultation->id, $patientAuditModalHtml);

            foreach ([
                PatientPrescriptionsRelationManager::class,
                PatientClinicalOrdersRelationManager::class,
                PatientReferralsRelationManager::class,
            ] as $manager) {
                Livewire::test($manager, [
                    'ownerRecord' => $patient,
                    'pageClass' => ViewPatient::class,
                ])->assertOk();
            }

            $consultationPage = Livewire::test(ViewMedicalConsultation::class, ['record' => $signedConsultation->getRouteKey()])
                ->assertOk()
                ->assertActionHidden('guardarBorrador')
                ->assertActionVisible('trazabilidadAuditada')
                ->mountAction('trazabilidadAuditada')
                ->assertActionMounted('trazabilidadAuditada');

            $consultationAuditModalHtml = (string) $consultationPage->instance()->getMountedAction()?->getModalContent()?->render();
            $this->assertStringContainsString('SIGN', $consultationAuditModalHtml);
            $this->assertStringContainsString((string) $signedConsultation->id, $consultationAuditModalHtml);

            $this->flushSession();
            $this->actingAs($recepcion);

            Livewire::test(ViewPatient::class, ['record' => $patient->getRouteKey()])
                ->assertActionHidden('trazabilidadAuditada');
        } finally {
            $connection->rollBack();
        }
    }

    public function test_sig05_sig09_sig10_y_sig13_borrador_firma_atomica_calendario_y_sin_soap(): void
    {
        $medico = User::query()->where('email', 'medico@sigcm.local')->firstOrFail();
        $connection = DB::connection('pgsql');
        $connection->beginTransaction();

        try {
            $this->actingAs($medico);

            $draft = MedicalConsultation::factory()->create([
                'patient_id' => 1,
                'doctor_id' => 1,
                'status' => ConsultationStatus::Draft,
            ]);

            Livewire::test(ViewMedicalConsultation::class, ['record' => $draft->getRouteKey()])
                ->assertActionVisible('guardarBorrador')
                ->callAction('guardarBorrador')
                ->assertNotified(__('sigcm.consultation.actions.draft_saved_ok'));

            // SIG-10: Registrar y firmar inmediatamente vía sp_registrar_y_firmar_consulta_segura desde UI
            Livewire::test(CreateMedicalConsultation::class)
                ->fillForm([
                    'patient_id' => 1,
                    'doctor_id' => 1,
                    'occurred_at' => now()->subMinutes(3)->format('Y-m-d H:i:s'),
                    'chief_complaint' => 'Control inmediato desde CreateMedicalConsultation',
                    'subjective' => 'Sin molestias actuales.',
                    'objective' => 'Exploración clínica estable.',
                    'assessment' => 'Evolución satisfactoria.',
                    'plan' => 'Control programado en 30 días.',
                ])
                ->callAction('registrarYFirmarAhora', data: [
                    'episode_title' => 'Episodio directo desde creación',
                ])
                ->assertNotified('Consulta registrada y firmada');

            $signedFromCreate = MedicalConsultation::query()
                ->where('chief_complaint', 'Control inmediato desde CreateMedicalConsultation')
                ->firstOrFail();

            $this->assertSame(ConsultationStatus::Signed, $signedFromCreate->status);
            $this->assertTrue(AuditEvent::query()
                ->where('entity_name', 'medical_consultations')
                ->where('entity_id', $signedFromCreate->id)
                ->where('operation', 'SIGN')
                ->where('actor_user_id', $medico->id)
                ->exists());

            // SIG-13: la impresión de consultas sembradas no debe contener la palabra SOAP
            $seededConsultation = MedicalConsultation::query()->orderBy('id')->firstOrFail();
            $this->get(route('sigcm.print.consultation', $seededConsultation))
                ->assertOk()
                ->assertDontSee('SOAP');

            // SIG-09: acciones contextuales en AgendaCalendarWidget con firma real de Guava
            $appointment = Appointment::factory()->create([
                'doctor_id' => 1,
                'patient_id' => 1,
                'starts_at' => '2026-12-23 09:00:00-04',
                'ends_at' => '2026-12-23 09:30:00-04',
                'status' => AppointmentStatus::Pending,
            ]);

            $calendar = Livewire::test(AgendaCalendarWidget::class);
            $fetchRange = [
                'startStr' => '2026-12-21T00:00:00-04:00',
                'endStr' => '2026-12-27T23:59:59-04:00',
                'tzOffset' => 240,
            ];
            $calendarViewPayload = [
                'type' => 'timeGridWeek',
                'title' => '21 – 27 dic 2026',
                'currentStart' => '2026-12-21T00:00:00-04:00',
                'currentEnd' => '2026-12-28T00:00:00-04:00',
                'activeStart' => '2026-12-21T00:00:00-04:00',
                'activeEnd' => '2026-12-28T00:00:00-04:00',
            ];

            $targetEvent = collect($calendar->instance()->getEventsJs($fetchRange))->first(
                fn (array $event): bool => (string) data_get($event, 'extendedProps.key') === (string) $appointment->id
            );
            $this->assertIsArray($targetEvent);

            $calendar
                ->call('onEventClickJs', ['event' => $targetEvent, 'tzOffset' => 240, 'view' => $calendarViewPayload], 'confirmarCita')
                ->assertNotified('Cita confirmada');

            $this->assertSame(AppointmentStatus::Confirmed, $appointment->fresh()?->status);

            $refreshedEvent = collect($calendar->instance()->getEventsJs($fetchRange))->first(
                fn (array $event): bool => (string) data_get($event, 'extendedProps.key') === (string) $appointment->id
            );
            $this->assertIsArray($refreshedEvent);

            $calendar
                ->call('onEventClickJs', ['event' => $refreshedEvent, 'tzOffset' => 240, 'view' => $calendarViewPayload], 'registrarLlegada')
                ->assertNotified('Paciente registrado en sala');

            $this->assertSame(AppointmentStatus::CheckedIn, $appointment->fresh()?->status);
        } finally {
            $connection->rollBack();
        }
    }

    public function test_sig15_user_resource_rbac_creacion_desactivacion_y_bloqueo_panel(): void
    {
        $admin = User::query()->where('email', 'admin@sigcm.local')->firstOrFail();
        $medico = User::query()->where('email', 'medico@sigcm.local')->firstOrFail();
        $recepcion = User::query()->where('email', 'recepcion@sigcm.local')->firstOrFail();
        $recepcionRole = Role::query()->where('name', 'recepcion')->firstOrFail();
        $connection = DB::connection('pgsql');
        $connection->beginTransaction();

        try {
            $this->actingAs($medico)->get('/admin/users')->assertForbidden();
            $this->flushSession();
            $this->actingAs($recepcion)->get('/admin/users')->assertForbidden();
            $this->flushSession();

            $this->actingAs($admin);
            $this->get('/admin/users')->assertOk()->assertSee(__('sigcm.nav.users'));

            Livewire::test(CreateUser::class)
                ->fillForm([
                    'name' => 'Recepción Turno Tarde',
                    'email' => 'recepcion2@sigcm.local',
                    'roles' => [$recepcionRole->id],
                    'is_active' => true,
                    'password' => 'ClaveSegura2026!',
                    'password_confirmation' => 'ClaveSegura2026!',
                ])
                ->call('create')
                ->assertHasNoFormErrors();

            $createdUser = User::query()->where('email', 'recepcion2@sigcm.local')->firstOrFail();
            $this->assertTrue($createdUser->hasRole('recepcion'));
            $this->assertTrue($createdUser->canAccessPanel(Filament::getPanel('admin')));

            Livewire::test(ListUsers::class)
                ->callTableAction('alternarAcceso', $createdUser);

            $disabledUser = $createdUser->fresh();
            $this->assertFalse($disabledUser->is_active);
            $this->assertFalse($disabledUser->canAccessPanel(Filament::getPanel('admin')));

            $this->flushSession();
            $this->actingAs($disabledUser)->get('/admin')->assertForbidden();
        } finally {
            $connection->rollBack();
        }
    }

    public function test_sig16_y_sig17_cita_consulta_enlazada_y_seguimientos_en_historia_clinica(): void
    {
        $medico = User::query()->where('email', 'medico@sigcm.local')->firstOrFail();
        $patient = Patient::query()->with('clinicalRecord')->orderBy('id')->firstOrFail();
        $record = ClinicalRecord::query()->where('patient_id', $patient->id)->firstOrFail();
        $completedAppointment = Appointment::query()
            ->where('patient_id', $patient->id)
            ->whereHas('medicalConsultation')
            ->with('medicalConsultation')
            ->firstOrFail();

        $connection = DB::connection('pgsql');
        $connection->beginTransaction();

        try {
            $this->actingAs($medico);

            // VerPaciente renderiza el grid de 3 columnas con CSS propio y sin nombres DEMO
            $this->get("/admin/patients/{$patient->id}")
                ->assertOk()
                ->assertSee('sigcm-summary-grid')
                ->assertSee($patient->full_name)
                ->assertDontSee('Paciente DEMO');

            // SIG-16: badge clicable hacia la consulta cuando la cita ya tiene consulta
            $this->get("/admin/appointments/{$completedAppointment->id}")
                ->assertOk()
                ->assertSee(MedicalConsultationResource::getUrl('view', ['record' => $completedAppointment->medicalConsultation]));

            // SIG-16: cita sin consulta ofrece "Atender consulta", transiciona a checked_in y redirige precargada
            $pendingAppointment = Appointment::factory()->create([
                'patient_id' => $patient->id,
                'doctor_id' => 1,
                'starts_at' => '2026-12-26 09:00:00-04',
                'ends_at' => '2026-12-26 09:30:00-04',
                'status' => AppointmentStatus::Confirmed,
                'reason' => 'Control ambulatorio en agenda',
            ]);

            Livewire::test(ViewAppointment::class, ['record' => $pendingAppointment->getRouteKey()])
                ->assertActionVisible('atenderConsulta')
                ->callAction('atenderConsulta')
                ->assertRedirect(MedicalConsultationResource::getUrl('create', ['appointment_id' => $pendingAppointment->id]));

            $this->assertSame(AppointmentStatus::CheckedIn, $pendingAppointment->fresh()?->status);

            $this->get('/admin/medical-consultations/create?appointment_id='.$pendingAppointment->id)
                ->assertOk()
                ->assertSee($patient->full_name);

            // SIG-17: Historia clínica expone todos los follow_up_plans, permite programar cita y cierra al firmar
            $plan = FollowUpPlan::query()
                ->whereHas('originatingConsultation', fn ($query) => $query->where('patient_id', $patient->id))
                ->where('status', 'pending')
                ->orderBy('id')
                ->firstOrFail();

            $this->get("/admin/clinical-records/{$record->id}")
                ->assertOk()
                ->assertSee(__('sigcm.clinical.all_follow_ups_tab'));

            Livewire::test(ClinicalFollowUpPlansRelationManager::class, [
                'ownerRecord' => $record,
                'pageClass' => ViewClinicalRecord::class,
            ])
                ->assertOk()
                ->assertCanSeeTableRecords([$plan])
                ->callTableAction('programarCitaControl', $plan, data: [
                    'doctor_id' => 1,
                    'starts_at' => '2026-12-27 10:00:00',
                    'ends_at' => '2026-12-27 10:30:00',
                ])
                ->assertNotified('Cita de control programada');

            $scheduledPlan = $plan->fresh();
            $this->assertSame('scheduled', $scheduledPlan->status);
            $this->assertNotNull($scheduledPlan->appointment_id);

            app(SigcmDatabaseService::class)->registrarYFirmarConsultaSegura([
                'patient_id' => $patient->id,
                'doctor_id' => 1,
                'appointment_id' => $scheduledPlan->appointment_id,
                'chief_complaint' => 'Evaluación de seguimiento programado',
                'subjective' => 'Paciente acude a control con registro de presión domiciliaria.',
                'objective' => 'PA 124/78 mmHg, sin edemas ni cefalea.',
                'assessment' => 'Hipertensión arterial en respuesta favorable.',
                'plan' => 'Continuar esquema vigente.',
                'episode_title' => 'Control de hipertensión arterial esencial',
                'care_episode_id' => $scheduledPlan->episode_id,
            ], $medico->id);

            $closedPlan = $scheduledPlan->fresh();
            $this->assertSame('completed', $closedPlan->status);
            $this->assertNotNull($closedPlan->resulting_consultation_id);
        } finally {
            $connection->rollBack();
        }
    }
}
