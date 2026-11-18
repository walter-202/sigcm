<?php

namespace App\Filament\Resources\Patients\Widgets;

use App\Filament\Resources\ClinicalRecords\ClinicalRecordResource;
use App\Models\Patient;
use Filament\Widgets\Widget;

class PatientSummaryWidget extends Widget
{
    protected static bool $isDiscovered = false;

    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 'full';

    protected string $view = 'filament.patients.widgets.patient-summary-widget';

    public ?Patient $record = null;

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $patient = $this->record?->loadMissing([
            'clinicalRecord',
            'allergies',
            'conditions',
        ]);

        if ($patient === null) {
            return [];
        }

        $activeAllergies = $patient->allergies
            ->where('clinical_status', 'active')
            ->values();

        $activeConditions = $patient->conditions
            ->where('clinical_status', 'active')
            ->values();

        $recordUrl = $patient->clinicalRecord !== null
            ? ClinicalRecordResource::getUrl('view', ['record' => $patient->clinicalRecord])
            : null;

        return [
            'patient' => $patient,
            'activeAllergies' => $activeAllergies,
            'activeConditions' => $activeConditions,
            'recordUrl' => $recordUrl,
        ];
    }
}
