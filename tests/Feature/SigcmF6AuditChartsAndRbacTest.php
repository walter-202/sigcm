<?php

namespace Tests\Feature;

use App\Filament\Resources\AuditEvents\AuditEventResource;
use App\Filament\Resources\AuditEvents\Pages\ListAuditEvents;
use App\Models\AuditEvent;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class SigcmF6AuditChartsAndRbacTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['database.default' => 'pgsql']);
        DB::purge('pgsql');
    }

    public function test_explorador_de_auditoria_es_solo_lectura_y_filtrable_para_super_admin(): void
    {
        $admin = User::query()->where('email', 'admin@sigcm.local')->firstOrFail();
        $medico = User::query()->where('email', 'medico@sigcm.local')->firstOrFail();
        $recepcion = User::query()->where('email', 'recepcion@sigcm.local')->firstOrFail();
        $sampleEvent = AuditEvent::query()->orderByDesc('id')->firstOrFail();

        $this->assertFalse(AuditEventResource::canCreate());
        $this->assertFalse(AuditEventResource::canEdit($sampleEvent));
        $this->assertFalse(AuditEventResource::canDelete($sampleEvent));

        $this->actingAs($admin)
            ->get('/admin/audit-events')
            ->assertOk()
            ->assertSee(__('sigcm.nav.audit_events'))
            ->assertSee('Diagnóstico de seguridad DCL');

        $this->actingAs($admin)
            ->get("/admin/audit-events/{$sampleEvent->id}")
            ->assertOk()
            ->assertSee('Detalle del evento de auditoría')
            ->assertSee($sampleEvent->entity_name);

        Livewire::test(ListAuditEvents::class)
            ->assertOk()
            ->assertTableActionExists('exportarAuditoria', null)
            ->filterTable('operation', $sampleEvent->operation)
            ->assertCanSeeTableRecords([$sampleEvent]);

        $this->flushSession();
        $this->actingAs($medico)->get('/admin/audit-events')->assertForbidden();
        $this->actingAs($medico)->get('/admin/bitacora-auditoria')->assertForbidden();

        $this->flushSession();
        $this->actingAs($recepcion)->get('/admin/audit-events')->assertForbidden();
        $this->actingAs($recepcion)->get('/admin/bitacora-auditoria')->assertForbidden();
    }

    public function test_escritorio_apexcharts_y_navegacion_por_rol_sin_referencias_academicas(): void
    {
        $admin = User::query()->where('email', 'admin@sigcm.local')->firstOrFail();
        $medico = User::query()->where('email', 'medico@sigcm.local')->firstOrFail();
        $recepcion = User::query()->where('email', 'recepcion@sigcm.local')->firstOrFail();

        foreach ([$admin, $medico, $recepcion] as $user) {
            $this->flushSession();
            $this->actingAs($user)
                ->get('/admin')
                ->assertOk()
                ->assertSee('Resumen clínico operativo')
                ->assertSee('Distribución de citas médicas por estado')
                ->assertSee('Consultas médicas por profesional')
                ->assertDontSee('Laboratorio BD II')
                ->assertDontSee('demo BD II');
        }

        // Recepción: agenda y pacientes sí; creación de consultas y roles no
        $this->flushSession();
        $this->actingAs($recepcion)->get('/admin/agenda-medica')->assertOk();
        $this->actingAs($recepcion)->get('/admin/patients')->assertOk();
        $this->actingAs($recepcion)->get('/admin/appointments')->assertOk();
        $this->actingAs($recepcion)->get('/admin/medical-consultations/create')->assertForbidden();
        $this->actingAs($recepcion)->get('/admin/shield/roles')->assertForbidden();

        // Médico: agenda, expediente y consulta sí; roles administrativos no
        $this->flushSession();
        $this->actingAs($medico)->get('/admin/agenda-medica')->assertOk();
        $this->actingAs($medico)->get('/admin/patients/1')->assertOk();
        $this->actingAs($medico)->get('/admin/clinical-records/1')->assertOk();
        $this->actingAs($medico)->get('/admin/medical-consultations/create')->assertOk();
        $this->actingAs($medico)->get('/admin/shield/roles')->assertForbidden();

        // Super Admin: acceso total incluyendo Shield y catálogo de médicos
        $this->flushSession();
        $this->actingAs($admin)->get('/admin/doctors')->assertOk();
        $this->actingAs($admin)->get('/admin/shield/roles')->assertOk();
    }
}
