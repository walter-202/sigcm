<?php

namespace App\Filament\Pages;

use App\Services\SigcmDatabaseService;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

class CatalogoMedicosPage extends Page
{
    protected static ?string $slug = 'catalogo-medicos';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static ?string $navigationLabel = '1. Catálogo Público (Vista)';

    protected static ?string $title = 'Catálogo Público de Médicos y Expedientes';

    protected static ?int $navigationSort = 1;

    protected string $view = 'filament.pages.catalogo-medicos-page';

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $service = app(SigcmDatabaseService::class);

        return [
            'medicos' => $service->getCatalogoMedicos(),
            'expedientes' => $service->getExpedientesResumen(),
            'asistenteUser' => (string) config('database.connections.pgsql_asistente.username', 'asistente_clinica_web'),
        ];
    }
}
