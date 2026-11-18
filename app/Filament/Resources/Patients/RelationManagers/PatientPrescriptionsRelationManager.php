<?php

namespace App\Filament\Resources\Patients\RelationManagers;

use App\Filament\Resources\MedicalConsultations\MedicalConsultationResource;
use App\Models\Prescription;
use Filament\Actions\Action;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class PatientPrescriptionsRelationManager extends RelationManager
{
    protected static string $relationship = 'prescriptions';

    protected static bool $isLazy = false;

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('sigcm.clinical.prescriptions_tab');
    }

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['consultation'])->withCount('items'))
            ->columns([
                TextColumn::make('id')
                    ->label(__('sigcm.fields.prescription'))
                    ->formatStateUsing(fn ($state): string => "REC-#{$state}")
                    ->sortable(),
                TextColumn::make('consultation_id')
                    ->label(__('sigcm.fields.consultation'))
                    ->formatStateUsing(fn ($state): string => "#{$state}")
                    ->url(fn (Prescription $record): string => MedicalConsultationResource::getUrl('view', ['record' => $record->consultation_id])),
                TextColumn::make('status')
                    ->label(__('sigcm.fields.status'))
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => __("enums.prescription_status.{$state}"))
                    ->color(fn (string $state): string => match ($state) {
                        'issued' => 'success',
                        'replaced' => 'warning',
                        'void' => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('items_count')
                    ->label(__('sigcm.fields.items_count'))
                    ->badge()
                    ->color('info'),
                TextColumn::make('issued_at')
                    ->label(__('sigcm.fields.issued_at'))
                    ->dateTime('d/m/Y H:i')
                    ->placeholder(__('enums.prescription_status.draft')),
                TextColumn::make('notes')
                    ->label(__('sigcm.fields.notes'))
                    ->limit(40)
                    ->placeholder('-'),
            ])
            ->defaultSort('prescriptions.id', 'desc')
            ->recordActions([
                Action::make('abrirConsulta')
                    ->label(__('sigcm.actions.view_detail'))
                    ->icon(Heroicon::OutlinedEye)
                    ->url(fn (Prescription $record): string => MedicalConsultationResource::getUrl('view', ['record' => $record->consultation_id])),
                Action::make('imprimir')
                    ->label(__('sigcm.clinical.print_prescription'))
                    ->icon(Heroicon::OutlinedPrinter)
                    ->url(fn (Prescription $record): string => route('sigcm.print.prescription', $record))
                    ->openUrlInNewTab(),
            ]);
    }
}
