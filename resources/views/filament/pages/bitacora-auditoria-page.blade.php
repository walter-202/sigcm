<x-filament-panels::page>
    @include('filament.components.bd-table-styles')

    @if ($dclCheck['blocked'])
        <x-filament::callout
            color="success"
            icon="heroicon-o-shield-check"
            heading="{{ __('sigcm.audit.dcl_verified_badge') }}"
            description="La cuenta operativa de consulta restringida ({{ $asistenteUser }}) no posee acceso de lectura ni modificación directa sobre la bitácora clínica ni sobre expedientes confidenciales (código de protección {{ $dclCheck['sqlstate'] }})."
        />
    @else
        <x-filament::callout
            color="danger"
            icon="heroicon-o-exclamation-triangle"
            heading="{{ __('sigcm.audit.dcl_alert_badge') }}"
            description="{{ $dclCheck['message'] }}"
        />
    @endif

    <x-filament::section
        heading="Registro de trazabilidad y auditoría clínica"
        description="Historial inmutable de acciones críticas registradas sobre expedientes, atenciones médicas, recetas, informes y documentos."
    >
        <div class="sigcm-sql-wrap">
            <table class="sigcm-sql-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Operación</th>
                        <th>Entidad</th>
                        <th>Registro</th>
                        <th>Usuario responsable</th>
                        <th>Detalle auditado</th>
                        <th>Fecha y hora</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($eventos as $evento)
                        @php
                            $opColor = match ($evento->operation) {
                                'SIGN' => 'success',
                                'INSERT' => 'info',
                                'UPDATE' => 'warning',
                                'AMEND' => 'primary',
                                'DELETE' => 'danger',
                                default => 'gray',
                            };
                        @endphp
                        <tr>
                            <td><strong>#{{ $evento->id }}</strong></td>
                            <td>
                                <x-filament::badge color="{{ $opColor }}">
                                    {{ $evento->operation }}
                                </x-filament::badge>
                            </td>
                            <td><span class="sigcm-code">{{ $evento->entity_name }}</span></td>
                            <td>#{{ $evento->entity_id }}</td>
                            <td><span class="sigcm-code">{{ $evento->actor_email }}</span></td>
                            <td><span class="sigcm-code">{{ $evento->changed_fields_json }}</span></td>
                            <td>{{ $evento->occurred_at }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" style="text-align: center;">No hay eventos de auditoría registrados.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-filament::section>
</x-filament-panels::page>
