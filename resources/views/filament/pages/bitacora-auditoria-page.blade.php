<x-filament-panels::page>
    @include('filament.components.bd-table-styles')

    @if ($dclCheck['blocked'])
        <x-filament::callout
            color="warning"
            icon="heroicon-o-shield-exclamation"
            heading="Bloqueo de seguridad verificado (DCL PostgreSQL 17)"
            description="El usuario restringido {{ $asistenteUser }} (rol_asistente_clinica) intentó ejecutar SELECT * FROM audit_events, pero el motor PostgreSQL 17 rechazó la consulta por falta de privilegios sobre la tabla física."
        >
            <x-slot name="footer">
                <div>
                    <strong>Respuesta del motor PostgreSQL (SQLSTATE {{ $dclCheck['sqlstate'] }}):</strong><br>
                    <span class="sigcm-code">{{ $dclCheck['message'] }}</span>
                </div>
            </x-slot>
        </x-filament::callout>
    @else
        <x-filament::callout
            color="danger"
            icon="heroicon-o-exclamation-triangle"
            heading="Atención: acceso directo no bloqueado"
            description="{{ $dclCheck['message'] }}"
        />
    @endif

    <x-filament::section
        heading="Vista de Supervisión: Eventos Registrados en audit_events"
        description="Consultado mediante la conexión administrativa para verificar la escritura automática de los triggers PL/pgSQL."
    >
        <x-slot name="afterHeader">
            <span class="sigcm-code">SELECT * FROM audit_events ORDER BY id DESC</span>
        </x-slot>

        <div class="sigcm-sql-wrap">
            <table class="sigcm-sql-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Operación</th>
                        <th>Entidad</th>
                        <th>ID Entidad</th>
                        <th>Actor</th>
                        <th>changed_fields (JSONB)</th>
                        <th>Fecha</th>
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
                            <td>{{ $evento->entity_id }}</td>
                            <td><span class="sigcm-code">{{ $evento->actor_email }}</span></td>
                            <td><span class="sigcm-code">{{ $evento->changed_fields_json }}</span></td>
                            <td>{{ $evento->occurred_at }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" style="text-align: center;">No hay eventos de auditoría disponibles.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-filament::section>
</x-filament-panels::page>
