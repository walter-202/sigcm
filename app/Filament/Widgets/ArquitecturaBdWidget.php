<?php

namespace App\Filament\Widgets;

use App\Services\SigcmDatabaseService;
use Filament\Widgets\Widget;

class ArquitecturaBdWidget extends Widget
{
    protected static ?int $sort = 2;

    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 'full';

    protected string $view = 'filament.widgets.arquitectura-bd-widget';

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        return [
            'summary' => app(SigcmDatabaseService::class)->getDashboardSummary(),
        ];
    }
}
