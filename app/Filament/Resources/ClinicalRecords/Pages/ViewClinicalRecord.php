<?php

namespace App\Filament\Resources\ClinicalRecords\Pages;

use App\Filament\Resources\ClinicalRecords\ClinicalRecordResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewClinicalRecord extends ViewRecord
{
    protected static string $resource = ClinicalRecordResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
