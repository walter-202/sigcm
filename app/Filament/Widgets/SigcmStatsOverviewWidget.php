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

    public function getHeading(): ?string
    {
        return __('sigcm.dashboard.heading');
    }

    public function getDescription(): ?string
    {
        return __('sigcm.dashboard.description');
    }

    /**
     * @return array<Stat>
     */
    protected function getStats(): array
    {
        $summary = app(SigcmDatabaseService::class)->getDashboardSummary();

        return [
            Stat::make(__('sigcm.dashboard.stats.active_doctors'), $summary['medicos_count'])
                ->description(__('sigcm.dashboard.stats.active_doctors_desc'))
                ->descriptionIcon(Heroicon::OutlinedUserGroup)
                ->color('primary'),

            Stat::make(
                __('sigcm.dashboard.stats.active_schedule'),
                __('sigcm.dashboard.stats.active_schedule_value', [
                    'citas' => $summary['cursor']['citas_activas'],
                    'horas' => $summary['cursor']['horas_agenda'],
                ])
            )
                ->description(__('sigcm.dashboard.stats.active_schedule_desc'))
                ->descriptionIcon(Heroicon::OutlinedCalendarDays)
                ->color('warning'),

            Stat::make(__('sigcm.dashboard.stats.signed_consultations'), $summary['cursor']['consultas_firmadas'])
                ->description(__('sigcm.dashboard.stats.signed_consultations_desc', [
                    'expedientes' => $summary['expedientes_count'],
                ]))
                ->descriptionIcon(Heroicon::OutlinedClipboardDocumentList)
                ->color('success'),

            Stat::make(__('sigcm.dashboard.stats.audit_events'), $summary['audit_events_count'])
                ->description(__('sigcm.dashboard.stats.audit_events_desc'))
                ->descriptionIcon(Heroicon::OutlinedShieldCheck)
                ->color('info'),
        ];
    }
}
