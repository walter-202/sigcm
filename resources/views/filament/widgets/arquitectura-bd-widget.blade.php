@php
    $privileges = $summary['privileges'] ?? [];
@endphp

<x-filament-widgets::widget>
    @include('filament.components.bd-table-styles')

    <x-filament::section
        heading="Arquitectura implementada en la materia (Base de Datos II · PostgreSQL 17)"
        description="Sesión restringida activa: {{ $summary['asistente_user'] }} · Rol: rol_asistente_clinica"
    >
        <x-slot name="afterHeader">
            <x-filament::badge color="{{ ($privileges['hereda_rol'] ?? false) && ! ($privileges['audit_events'] ?? true) ? 'success' : 'warning' }}">
                DCL: {{ ($privileges['hereda_rol'] ?? false) && ! ($privileges['audit_events'] ?? true) ? 'Privilegios mínimos verificados' : 'Verificar privilegios' }}
            </x-filament::badge>
        </x-slot>

        <div class="sigcm-sql-wrap">
            <table class="sigcm-sql-table">
                <thead>
                    <tr>
                        <th>Componente SQL</th>
                        <th>Objeto en PostgreSQL 17</th>
                        <th>Estado de sincronización / verificación</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><strong>Modelo Relacional</strong></td>
                        <td>
                            <span class="sigcm-code">26 tablas clínicas normalizadas</span> +
                            <span class="sigcm-code">5 tablas RBAC (Spatie)</span>
                        </td>
                        <td>Conectado a <span class="sigcm-code">db_sigcm</span> (puerto 5433)</td>
                    </tr>
                    <tr>
                        <td><strong>Automatización (Triggers)</strong></td>
                        <td>
                            <span class="sigcm-code">trg_validar_inmutabilidad_consulta</span>,
                            <span class="sigcm-code">trg_validar_solapamiento_cita</span>,
                            <span class="sigcm-code">trg_auditoria_consultas</span>,
                            <span class="sigcm-code">trg_auditoria_citas</span>,
                            <span class="sigcm-code">trg_auditoria_enmiendas</span>
                        </td>
                        <td>Activos en motor PL/pgSQL (<span class="sigcm-code">{{ $summary['audit_events_count'] }}</span> eventos en bitácora)</td>
                    </tr>
                    <tr>
                        <td><strong>Control Transaccional y Cursor</strong></td>
                        <td>
                            <span class="sigcm-code">sp_registrar_y_firmar_consulta_segura</span>,
                            <span class="sigcm-code">sp_reprogramar_cita_segura</span>,
                            <span class="sigcm-code">sp_valorizar_actividad_clinica</span>
                        </td>
                        <td>Soporte <span class="sigcm-code">FOR UPDATE</span>, <span class="sigcm-code">EXCEPTION</span> y <span class="sigcm-code">CURSOR</span> explícito</td>
                    </tr>
                    <tr>
                        <td><strong>Seguridad DCL (Mínimo Privilegio)</strong></td>
                        <td>
                            <span class="sigcm-code">rol_asistente_clinica</span> →
                            <span class="sigcm-code">{{ $summary['asistente_user'] }}</span>
                        </td>
                        <td>
                            Lectura en vistas:
                            <x-filament::badge size="sm" color="{{ ($privileges['catalogo_medicos'] ?? false) ? 'success' : 'danger' }}">vw_catalogo_medicos_publico</x-filament::badge>
                            <x-filament::badge size="sm" color="{{ ($privileges['agenda_detalle'] ?? false) ? 'success' : 'danger' }}">vw_agenda_consultas_detalle</x-filament::badge>
                            <x-filament::badge size="sm" color="{{ ($privileges['expedientes'] ?? false) ? 'success' : 'danger' }}">vw_expedientes_episodios_resumen</x-filament::badge>
                            · Tabla <span class="sigcm-code">audit_events</span>:
                            <x-filament::badge size="sm" color="{{ ! ($privileges['audit_events'] ?? true) ? 'success' : 'danger' }}">
                                {{ ! ($privileges['audit_events'] ?? true) ? 'BLOQUEADA (42501)' : 'EXPUESTA' }}
                            </x-filament::badge>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
