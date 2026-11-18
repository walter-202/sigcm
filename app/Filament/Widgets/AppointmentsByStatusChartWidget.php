<?php

namespace App\Filament\Widgets;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use Illuminate\Support\Facades\DB;
use Leandrocfe\FilamentApexCharts\Widgets\ApexChartWidget;

class AppointmentsByStatusChartWidget extends ApexChartWidget
{
    protected static ?int $sort = 2;

    protected static ?string $chartId = 'appointmentsByStatusChart';

    protected static bool $isLazy = false;

    protected static ?string $heading = 'Distribución de citas médicas por estado';

    /**
     * @return array<string, mixed>
     */
    protected function getOptions(): array
    {
        $counts = Appointment::query()
            ->select('status', DB::raw('COUNT(*)::int as total'))
            ->groupBy('status')
            ->pluck('total', 'status')
            ->all();

        $labels = [];
        $series = [];

        foreach (AppointmentStatus::cases() as $status) {
            $labels[] = $status->getLabel();
            $series[] = (int) ($counts[$status->value] ?? 0);
        }

        return [
            'chart' => [
                'type' => 'donut',
                'height' => 280,
            ],
            'series' => $series,
            'labels' => $labels,
            'colors' => ['#f59e0b', '#3b82f6', '#14b8a6', '#22c55e', '#ef4444', '#64748b'],
            'legend' => [
                'position' => 'bottom',
            ],
        ];
    }
}
