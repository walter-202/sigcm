<?php

namespace App\Filament\Resources\Patients\RelationManagers;

use App\Enums\ConsultationStatus;
use App\Filament\Resources\MedicalConsultations\MedicalConsultationResource;
use App\Models\MedicalConsultation;
use Filament\Actions\Action;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class MedicalConsultationsRelationManager extends RelationManager
{
    protected static string $relationship = 'medicalConsultations';

    protected static bool $isLazy = false;

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('sigcm.patients.consultations_tab');
    }

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('chief_complaint')
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with([
                'doctor',
                'careEpisodes',
                'diagnoses',
                'prescriptions',
            ]))
            ->columns([
                TextColumn::make('id')
                    ->label(__('sigcm.fields.id'))
                    ->formatStateUsing(fn ($state): string => "#{$state}")
                    ->sortable(),
                TextColumn::make('occurred_at')
                    ->label(__('sigcm.fields.occurred_at'))
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
                TextColumn::make('chief_complaint')
                    ->label(__('sigcm.fields.chief_complaint'))
                    ->limit(45)
                    ->searchable(),
                TextColumn::make('doctor.full_name')
                    ->label(__('sigcm.fields.attending_doctor'))
                    ->searchable(['first_names', 'last_names']),
                TextColumn::make('careEpisodes.title')
                    ->label(__('sigcm.fields.episode'))
                    ->badge()
                    ->color('info')
                    ->placeholder('Sin episodio'),
                TextColumn::make('diagnoses_count')
                    ->label(__('sigcm.fields.diagnoses_entries'))
                    ->counts('diagnoses')
                    ->badge()
                    ->color('gray'),
                TextColumn::make('prescriptions_count')
                    ->label(__('sigcm.clinical.prescriptions_tab'))
                    ->counts('prescriptions')
                    ->badge()
                    ->color('primary'),
                TextColumn::make('status')
                    ->label(__('sigcm.fields.status'))
                    ->badge()
                    ->sortable(),
            ])
            ->defaultSort('occurred_at', 'desc')
            ->headerActions([
                Action::make('nuevaConsulta')
                    ->label(__('sigcm.actions.new_consultation'))
                    ->icon(Heroicon::OutlinedClipboardDocumentCheck)
                    ->url(fn (): string => MedicalConsultationResource::getUrl('create', [
                        'patient_id' => $this->getOwnerRecord()->getKey(),
                    ]))
                    ->visible(fn (): bool => auth()->user()?->can('create', MedicalConsultation::class) ?? false),
            ])
            ->recordActions([
                Action::make('verAtencion')
                    ->label(__('sigcm.actions.view_detail'))
                    ->icon(Heroicon::OutlinedEye)
                    ->url(fn (MedicalConsultation $record): string => MedicalConsultationResource::getUrl('view', ['record' => $record])),
                Action::make('editarBorrador')
                    ->label(__('sigcm.actions.edit_draft'))
                    ->icon(Heroicon::OutlinedPencilSquare)
                    ->url(fn (MedicalConsultation $record): string => MedicalConsultationResource::getUrl('edit', ['record' => $record]))
                    ->visible(fn (MedicalConsultation $record): bool => $record->status === ConsultationStatus::Draft
                        && (auth()->user()?->can('update', $record) ?? false)),
            ]);
    }
}
