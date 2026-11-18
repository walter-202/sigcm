<?php

namespace App\Filament\Resources\Patients;

use App\Filament\Resources\ClinicalRecords\RelationManagers\ClinicalFollowUpPlansRelationManager;
use App\Filament\Resources\Patients\Pages\CreatePatient;
use App\Filament\Resources\Patients\Pages\EditPatient;
use App\Filament\Resources\Patients\Pages\ListPatients;
use App\Filament\Resources\Patients\Pages\ViewPatient;
use App\Filament\Resources\Patients\RelationManagers\AllergiesRelationManager;
use App\Filament\Resources\Patients\RelationManagers\AppointmentsRelationManager;
use App\Filament\Resources\Patients\RelationManagers\ConditionsRelationManager;
use App\Filament\Resources\Patients\RelationManagers\DocumentsRelationManager;
use App\Filament\Resources\Patients\RelationManagers\MedicalConsultationsRelationManager;
use App\Filament\Resources\Patients\RelationManagers\MedicationsRelationManager;
use App\Filament\Resources\Patients\RelationManagers\PatientClinicalOrdersRelationManager;
use App\Filament\Resources\Patients\RelationManagers\PatientPrescriptionsRelationManager;
use App\Filament\Resources\Patients\RelationManagers\PatientReferralsRelationManager;
use App\Filament\Resources\Patients\Schemas\PatientForm;
use App\Filament\Resources\Patients\Schemas\PatientInfolist;
use App\Filament\Resources\Patients\Tables\PatientsTable;
use App\Models\Patient;
use BackedEnum;
use Filament\Resources\RelationManagers\RelationGroup;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class PatientResource extends Resource
{
    protected static ?string $model = Patient::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static ?string $modelLabel = 'Paciente';

    protected static ?string $pluralModelLabel = 'Pacientes';

    protected static ?string $recordTitleAttribute = 'full_name';

    protected static ?int $navigationSort = 2;

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('sigcm.nav.clinical_management');
    }

    public static function getNavigationLabel(): string
    {
        return __('sigcm.nav.patients');
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['first_names', 'last_names', 'document_number', 'phone'];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with([
            'clinicalRecord.careEpisodes.conditions',
            'clinicalRecord.careEpisodes.consultations',
        ]);
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return PatientForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return PatientInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PatientsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            MedicalConsultationsRelationManager::class,
            RelationGroup::make(fn (): string => __('sigcm.patients.appointments_tab'), [
                AppointmentsRelationManager::class,
                ClinicalFollowUpPlansRelationManager::class,
            ]),
            RelationGroup::make(fn (): string => __('sigcm.patients.clinical_orders_tab'), [
                PatientPrescriptionsRelationManager::class,
                PatientClinicalOrdersRelationManager::class,
                PatientReferralsRelationManager::class,
            ]),
            AllergiesRelationManager::class,
            ConditionsRelationManager::class,
            MedicationsRelationManager::class,
            DocumentsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPatients::route('/'),
            'create' => CreatePatient::route('/create'),
            'view' => ViewPatient::route('/{record}'),
            'edit' => EditPatient::route('/{record}/edit'),
        ];
    }
}
