<?php

namespace App\Filament\Widgets;

use App\Services\SigcmDatabaseService;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class SigcmStatsOverviewWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected static bool $isLazy = false;

    protected ?string $heading = 'Resumen ejecutivo en tiempo real';

    protected ?string $description = 'Métricas consultadas mediante vistas restringidas (asistente_clinica_web) y cursor PL/pgSQL en PostgreSQL 17.';

    /**
     * @return array<Stat>
     */
    protected function getStats(): array
    {
        $summary = app(SigcmDatabaseService::class)->getDashboardSummary();

        return [
            Stat::make('Catálogo de Médicos', $summary['medicos_count'])
                ->description('SELECT COUNT(*) FROM vw_catalogo_medicos_publico')
                ->descriptionIcon(Heroicon::OutlinedUserGroup)
                ->color('primary'),

            Stat::make('Citas Activas / Horas (Cursor)', "{$summary['cursor']['citas_activas']} citas · {$summary['cursor']['horas_agenda']} h")
                ->description('CALL sp_valorizar_actividad_clinica()')
                ->descriptionIcon(Heroicon::OutlinedCalendarDays)
                ->color('warning'),

            Stat::make('Consultas Firmadas (Inmutables)', $summary['cursor']['consultas_firmadas'])
                ->description("Expedientes abiertos: {$summary['expedientes_count']}")
                ->descriptionIcon(Heroicon::OutlinedClipboardDocumentList)
                ->color('success'),

            Stat::make('Eventos en Bitácora (audit_events)', $summary['audit_events_count'])
                ->description('Registro automático por triggers PL/pgSQL')
                ->descriptionIcon(Heroicon::OutlinedShieldCheck)
                ->color('info'),
        ];
    }
}
