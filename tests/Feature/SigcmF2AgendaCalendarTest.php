<?php

namespace Tests\Feature;

use App\Enums\AppointmentStatus;
use App\Filament\Pages\AgendaMedicaPage;
use App\Filament\Widgets\AgendaCalendarWidget;
use App\Models\Appointment;
use App\Models\AppointmentReschedule;
use App\Models\User;
use App\Services\SigcmDatabaseService;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class SigcmF2AgendaCalendarTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['database.default' => 'pgsql']);
        DB::purge('pgsql');
    }

    public function test_pagina_agenda_medica_y_widget_guava_renderizan_por_rol(): void
    {
        $recepcion = User::query()->where('email', 'recepcion@sigcm.local')->firstOrFail();

        $this->actingAs($recepcion)
            ->get('/admin/agenda-medica')
            ->assertOk()
            ->assertSee(__('sigcm.schedule.title'))
            ->assertSee(__('sigcm.schedule.filter_doctor'))
            ->assertSee(__('sigcm.schedule.table_view'));

        Livewire::test(AgendaMedicaPage::class)
            ->set('doctorId', 1)
            ->assertDispatched('agenda-filter-updated');
    }

    public function test_widget_calendario_carga_eventos_y_crea_cita_respetando_gist(): void
    {
        $recepcion = User::query()->where('email', 'recepcion@sigcm.local')->firstOrFail();
        $connection = DB::connection('pgsql');
        $connection->beginTransaction();

        try {
            $this->actingAs($recepcion);

            $appointment = Appointment::factory()->create([
                'doctor_id' => 1,
                'patient_id' => 1,
                'starts_at' => '2026-12-22 09:00:00-04',
                'ends_at' => '2026-12-22 09:30:00-04',
                'status' => AppointmentStatus::Confirmed,
                'reason' => 'Control programado Guava',
            ]);

            $component = Livewire::test(AgendaCalendarWidget::class)
                ->call('applyFilters', 1, null)
                ->assertSet('doctorId', 1);

            $events = $component->instance()->getEventsJs([
                'startStr' => '2026-12-21T00:00:00-04:00',
                'endStr' => '2026-12-27T23:59:59-04:00',
                'tzOffset' => 240,
            ]);

            $this->assertNotEmpty($events);
            $titles = collect($events)->pluck('title')->implode(' ');
            $this->assertStringContainsString($appointment->patient->last_names, $titles);

            // Creación válida fuera de bloque ocupado
            $component->callAction('createAppointment', data: [
                'patient_id' => 2,
                'doctor_id' => 1,
                'starts_at' => '2026-12-22 11:00:00',
                'ends_at' => '2026-12-22 11:30:00',
                'status' => AppointmentStatus::Pending->value,
                'reason' => 'Cita creada desde calendario',
            ])->assertNotified(__('sigcm.schedule.created_ok'));

            $this->assertDatabaseHas('appointments', [
                'patient_id' => 2,
                'doctor_id' => 1,
                'reason' => 'Cita creada desde calendario',
            ]);

            // Intento solapado con la cita confirmada (09:00 - 09:30) debe notificar error sin romper
            $component->callAction('createAppointment', data: [
                'patient_id' => 2,
                'doctor_id' => 1,
                'starts_at' => '2026-12-22 09:10:00',
                'ends_at' => '2026-12-22 09:40:00',
                'status' => AppointmentStatus::Pending->value,
                'reason' => 'Solapamiento esperado',
            ])->assertNotified(__('sigcm.schedule.create_error'));

            // Reprogramación directa vía sp_reprogramar_cita_segura
            $this->assertDatabaseMissing('appointment_reschedules', [
                'appointment_id' => $appointment->id,
            ]);

            $reprogrammed = Appointment::query()->where('reason', 'Cita creada desde calendario')->firstOrFail();
            app(SigcmDatabaseService::class)->reprogramarCitaSegura(
                appointmentId: (int) $reprogrammed->id,
                changedByUserId: $recepcion->id,
                newStart: '2026-12-22 12:00:00-04',
                newEnd: '2026-12-22 12:30:00-04',
                reason: 'Cambio de horario en calendario',
            );

            $this->assertSame(
                1,
                AppointmentReschedule::query()->where('appointment_id', $reprogrammed->id)->count()
            );
        } finally {
            $connection->rollBack();
        }
    }
}
