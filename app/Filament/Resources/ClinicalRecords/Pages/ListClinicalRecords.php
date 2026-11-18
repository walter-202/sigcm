<?php

namespace App\Filament\Resources\ClinicalRecords\Pages;

use App\Filament\Resources\ClinicalRecords\ClinicalRecordResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListClinicalRecords extends ListRecords
{
    protected static string $resource = ClinicalRecordResource::class;

    public static function getNavigationLabel(): string
    {
        return __('sigcm.spotlight.list');
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
