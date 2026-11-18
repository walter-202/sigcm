<?php

namespace App\Filament\Widgets;

use App\Models\Doctor;
use Leandrocfe\FilamentApexCharts\Widgets\ApexChartWidget;

class ClinicalActivityByDoctorChartWidget extends ApexChartWidget
{
    protected static ?int $sort = 3;

    protected static ?string $chartId = 'clinicalActivityByDoctorChart';

    protected static bool $isLazy = false;

    protected static ?string $heading = 'Consultas médicas por profesional';

    /**
     * @return array<string, mixed>
     */
    protected function getOptions(): array
    {
        $doctors = Doctor::query()
            ->where('is_active', true)
            ->withCount([
                'medicalConsultations as signed_count' => fn ($query) => $query->where('status', 'signed'),
                'medicalConsultations as draft_count' => fn ($query) => $query->where('status', 'draft'),
            ])
            ->orderBy('id')
            ->limit(8)
            ->get();

        return [
            'chart' => [
                'type' => 'bar',
                'height' => 280,
                'stacked' => true,
            ],
            'series' => [
                [
                    'name' => 'Firmadas',
                    'data' => $doctors->pluck('signed_count')->map(fn ($v): int => (int) $v)->all(),
                ],
                [
                    'name' => 'Borrador',
                    'data' => $doctors->pluck('draft_count')->map(fn ($v): int => (int) $v)->all(),
                ],
            ],
            'xaxis' => [
                'categories' => $doctors->pluck('last_names')->all(),
            ],
            'colors' => ['#0f766e', '#f59e0b'],
            'plotOptions' => [
                'bar' => [
                    'horizontal' => false,
                    'borderRadius' => 4,
                ],
            ],
        ];
    }
}
