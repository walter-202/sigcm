<x-filament-panels::page>
    @include('filament.components.bd-table-styles')

    <x-filament::callout
        color="info"
        icon="heroicon-o-calendar-days"
        heading="Vista consolidada de citas, consultas e historia clínica"
        description="Replica syslab/prestamo.php consumiendo vw_agenda_consultas_detalle como asistente_clinica_web y ejecutando transacciones PL/pgSQL mediante las acciones superiores."
    />

    <x-filament::section heading="Historial Consolidado de Citas y Consultas">
        <x-slot name="afterHeader">
            <span class="sigcm-code">SELECT * FROM vw_agenda_consultas_detalle</span>
        </x-slot>

        <div class="sigcm-sql-wrap">
            <table class="sigcm-sql-table">
                <thead>
                    <tr>
                        <th>Código Cita</th>
                        <th>Paciente</th>
                        <th>Médico</th>
                        <th>Especialidad</th>
                        <th>Horario</th>
                        <th>Estado Cita</th>
                        <th>Consulta</th>
                        <th>Expediente</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($registros as $fila)
                        @php
                            $citaColor = match ($fila->estado_cita) {
                                'completed' => 'success',
                                'confirmed', 'checked_in' => 'info',
                                'pending' => 'warning',
                                'cancelled', 'no_show' => 'danger',
                                default => 'gray',
                            };

                            $consultaColor = match ($fila->estado_consulta) {
                                'signed' => 'success',
                                'draft' => 'warning',
                                default => 'gray',
                            };
                        @endphp
                        <tr>
                            <td><strong>{{ $fila->codigo_cita }}</strong></td>
                            <td>
                                <div>{{ $fila->paciente }}</div>
                                <small style="opacity: 0.75;">{{ $fila->documento_paciente }}</small>
                            </td>
                            <td>{{ $fila->medico }}</td>
                            <td>{{ $fila->especialidad }}</td>
                            <td>
                                <div>{{ $fila->starts_at }}</div>
                                <small style="opacity: 0.75;">hasta {{ $fila->ends_at }}</small>
                            </td>
                            <td>
                                <x-filament::badge color="{{ $citaColor }}">
                                    {{ $fila->estado_cita }}
                                </x-filament::badge>
                            </td>
                            <td>
                                <x-filament::badge color="{{ $consultaColor }}">
                                    {{ $fila->consultation_id ? "#{$fila->consultation_id} ({$fila->estado_consulta})" : 'Sin consulta' }}
                                </x-filament::badge>
                            </td>
                            <td>
                                <x-filament::badge color="{{ $fila->tiene_expediente ? 'success' : 'gray' }}">
                                    {{ $fila->tiene_expediente ? 'Abierto' : 'Sin abrir' }}
                                </x-filament::badge>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" style="text-align: center;">No existen citas registradas en la vista pública.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-filament::section>
</x-filament-panels::page>
