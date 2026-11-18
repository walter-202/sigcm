<?php

namespace App\Filament\Resources\MedicalConsultations;

use App\Enums\ConsultationStatus;
use App\Enums\EpisodeStatus;
use App\Filament\Resources\MedicalConsultations\Pages\CreateMedicalConsultation;
use App\Filament\Resources\MedicalConsultations\Pages\EditMedicalConsultation;
use App\Filament\Resources\MedicalConsultations\Pages\ListMedicalConsultations;
use App\Filament\Resources\MedicalConsultations\Pages\ViewMedicalConsultation;
use App\Filament\Resources\MedicalConsultations\RelationManagers\AmendmentsRelationManager;
use App\Filament\Resources\MedicalConsultations\RelationManagers\ClinicalOrdersRelationManager;
use App\Filament\Resources\MedicalConsultations\RelationManagers\OriginatedFollowUpPlansRelationManager;
use App\Filament\Resources\MedicalConsultations\RelationManagers\PrescriptionsRelationManager;
use App\Filament\Resources\MedicalConsultations\RelationManagers\ReferralsRelationManager;
use App\Filament\Resources\MedicalConsultations\Schemas\MedicalConsultationForm;
use App\Filament\Resources\MedicalConsultations\Schemas\MedicalConsultationInfolist;
use App\Filament\Resources\MedicalConsultations\Tables\MedicalConsultationsTable;
use App\Models\CareEpisode;
use App\Models\MedicalConsultation;
use App\Services\SigcmDatabaseService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Throwable;
use UnitEnum;

class MedicalConsultationResource extends Resource
{
    protected static ?string $model = MedicalConsultation::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    protected static ?string $modelLabel = 'Consulta Médica';

    protected static ?string $pluralModelLabel = 'Consultas Médicas';

    protected static ?int $navigationSort = 3;

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('sigcm.nav.clinical_management');
    }

    public static function getNavigationLabel(): string
    {
        return __('sigcm.nav.consultations');
    }

    public static function canEdit(Model $record): bool
    {
        return parent::canEdit($record)
            && $record instanceof MedicalConsultation
            && $record->status !== ConsultationStatus::Signed;
    }

    public static function canDelete(Model $record): bool
    {
        return parent::canDelete($record)
            && $record instanceof MedicalConsultation
            && $record->status !== ConsultationStatus::Signed;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return MedicalConsultationForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return MedicalConsultationInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return MedicalConsultationsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            PrescriptionsRelationManager::class,
            ClinicalOrdersRelationManager::class,
            ReferralsRelationManager::class,
            OriginatedFollowUpPlansRelationManager::class,
            AmendmentsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMedicalConsultations::route('/'),
            'create' => CreateMedicalConsultation::route('/create'),
            'view' => ViewMedicalConsultation::route('/{record}'),
            'edit' => EditMedicalConsultation::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with([
            'patient',
            'doctor',
            'appointment',
            'observations',
            'diagnoses',
        ]);
    }

    /**
     * Acción compartida para firmar una consulta en borrador y abrir o continuar un episodio clínico.
     */
    public static function makeSignAction(): Action
    {
        return Action::make('firmarConsulta')
            ->label(__('sigcm.consultation.actions.sign'))
            ->icon(Heroicon::OutlinedShieldCheck)
            ->color('success')
            ->visible(fn (MedicalConsultation $record): bool => $record->status === ConsultationStatus::Draft)
            ->modalHeading('Confirmar firma definitiva de la consulta')
            ->modalDescription('Al firmar la nota clínica pasará a estado inmutable, se asegurará la historia clínica del paciente y se vinculará al episodio correspondiente.')
            ->schema([
                Select::make('care_episode_id')
                    ->label(__('sigcm.fields.care_episode'))
                    ->options(fn (MedicalConsultation $record): array => CareEpisode::query()
                        ->whereHas('clinicalRecord', fn (Builder $query): Builder => $query->where('patient_id', $record->patient_id))
                        ->where('status', EpisodeStatus::Active->value)
                        ->orderByDesc('opened_at')
                        ->pluck('title', 'id')
                        ->all())
                    ->placeholder('Abrir nuevo episodio clínico')
                    ->nullable(),
                TextInput::make('episode_title')
                    ->label(__('sigcm.fields.episode_title'))
                    ->default('Episodio clínico ambulatorio')
                    ->required()
                    ->maxLength(180),
            ])
            ->action(function (MedicalConsultation $record, array $data, SigcmDatabaseService $service): void {
                try {
                    $episodeId = filled($data['care_episode_id'] ?? null)
                        ? (int) $data['care_episode_id']
                        : null;

                    $service->firmarConsultaExistente(
                        consultation: $record,
                        actorUserId: Auth::id(),
                        episodeTitle: (string) ($data['episode_title'] ?? 'Episodio clínico ambulatorio'),
                        careEpisodeId: $episodeId,
                    );

                    Notification::make()
                        ->title('Consulta firmada y expediente actualizado')
                        ->body('La nota médica fue firmada definitivamente y vinculada al expediente del paciente.')
                        ->success()
                        ->send();
                } catch (Throwable $exception) {
                    Notification::make()
                        ->title('No se pudo firmar la consulta')
                        ->body($service->formatDatabaseException($exception))
                        ->danger()
                        ->persistent()
                        ->send();
                }
            });
    }
}
