<x-filament-panels::page>
    @include('filament.components.bd-table-styles')

    <x-filament::callout
        color="info"
        icon="heroicon-o-eye"
        heading="Lectura exclusiva por vista SQL (DCL)"
        description="Esta página replica syslab/equipos.php ejecutando consultas sobre vistas públicas mediante la conexión restringida {{ $asistenteUser }} (rol_asistente_clinica), sin acceso directo a tablas sensibles."
    />

    <x-filament::section heading="Catálogo Público de Médicos">
        <x-slot name="afterHeader">
            <span class="sigcm-code">SELECT * FROM vw_catalogo_medicos_publico</span>
        </x-slot>

        <div class="sigcm-sql-wrap">
            <table class="sigcm-sql-table">
                <thead>
                    <tr>
                        <th>Matrícula Profesional</th>
                        <th>Médico</th>
                        <th>Especialidad</th>
                        <th>Estado</th>
                        <th>Citas</th>
                        <th>Consultas Firmadas</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($medicos as $medico)
                        <tr>
                            <td><strong>{{ $medico->professional_license }}</strong></td>
                            <td>{{ $medico->nombre_completo }}</td>
                            <td>
                                <x-filament::badge color="info">
                                    {{ $medico->specialty }}
                                </x-filament::badge>
                            </td>
                            <td>
                                <x-filament::badge color="{{ $medico->is_active ? 'success' : 'gray' }}">
                                    {{ $medico->is_active ? 'Activo' : 'Inactivo' }}
                                </x-filament::badge>
                            </td>
                            <td>{{ $medico->total_citas }}</td>
                            <td>{{ $medico->consultas_firmadas }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" style="text-align: center;">No existen médicos registrados en la vista pública.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-filament::section>

    <x-filament::section heading="Resumen Público de Expedientes Longitudinales">
        <x-slot name="afterHeader">
            <span class="sigcm-code">SELECT * FROM vw_expedientes_episodios_resumen</span>
        </x-slot>

        <div class="sigcm-sql-wrap">
            <table class="sigcm-sql-table">
                <thead>
                    <tr>
                        <th>Código HC</th>
                        <th>Paciente</th>
                        <th>Documento</th>
                        <th>Apertura</th>
                        <th>Consulta Apertura</th>
                        <th>Episodios</th>
                        <th>Seguimientos Pendientes</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($expedientes as $expediente)
                        <tr>
                            <td><strong>{{ $expediente->codigo_historia }}</strong></td>
                            <td>{{ $expediente->paciente }}</td>
                            <td>{{ $expediente->documento_paciente }}</td>
                            <td>{{ $expediente->opened_at }}</td>
                            <td>
                                <span class="sigcm-code">#{{ $expediente->consultation_apertura_id }}</span>
                                <x-filament::badge size="sm" color="success">{{ $expediente->estado_consulta_apertura }}</x-filament::badge>
                            </td>
                            <td>{{ $expediente->total_episodios }}</td>
                            <td>{{ $expediente->seguimientos_pendientes }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" style="text-align: center;">No existen expedientes clínicos en la vista pública.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-filament::section>
</x-filament-panels::page>
