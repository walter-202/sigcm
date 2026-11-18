<?php

namespace App\Filament\Resources\ClinicalRecords;

use App\Filament\Resources\ClinicalRecords\Pages\EditClinicalRecord;
use App\Filament\Resources\ClinicalRecords\Pages\ListClinicalRecords;
use App\Filament\Resources\ClinicalRecords\Pages\ViewClinicalRecord;
use App\Filament\Resources\ClinicalRecords\RelationManagers\CareEpisodesRelationManager;
use App\Filament\Resources\ClinicalRecords\RelationManagers\ClinicalFollowUpPlansRelationManager;
use App\Filament\Resources\ClinicalRecords\Schemas\ClinicalRecordForm;
use App\Filament\Resources\ClinicalRecords\Schemas\ClinicalRecordInfolist;
use App\Filament\Resources\ClinicalRecords\Tables\ClinicalRecordsTable;
use App\Models\ClinicalRecord;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class ClinicalRecordResource extends Resource
{
    protected static ?string $model = ClinicalRecord::class;

    protected static bool $shouldRegisterNavigation = false;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedFolderOpen;

    protected static ?string $modelLabel = 'Historia Clínica';

    protected static ?string $pluralModelLabel = 'Historias Clínicas';

    protected static ?int $navigationSort = 5;

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('sigcm.nav.clinical_management');
    }

    public static function getNavigationLabel(): string
    {
        return __('sigcm.nav.clinical_records');
    }

    public static function canCreate(): bool
    {
        return false;
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
        return ClinicalRecordForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return ClinicalRecordInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ClinicalRecordsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            CareEpisodesRelationManager::class,
            ClinicalFollowUpPlansRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListClinicalRecords::route('/'),
            'view' => ViewClinicalRecord::route('/{record}'),
            'edit' => EditClinicalRecord::route('/{record}/edit'),
        ];
    }
}
