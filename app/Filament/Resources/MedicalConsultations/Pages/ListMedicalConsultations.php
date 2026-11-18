<?php

namespace App\Filament\Resources\MedicalConsultations\Pages;

use App\Filament\Resources\MedicalConsultations\MedicalConsultationResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListMedicalConsultations extends ListRecords
{
    protected static string $resource = MedicalConsultationResource::class;

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
