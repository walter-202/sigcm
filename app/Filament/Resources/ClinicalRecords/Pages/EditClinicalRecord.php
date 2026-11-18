<?php

namespace App\Filament\Resources\ClinicalRecords\Pages;

use App\Filament\Resources\ClinicalRecords\ClinicalRecordResource;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditClinicalRecord extends EditRecord
{
    protected static string $resource = ClinicalRecordResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
        ];
    }
}
