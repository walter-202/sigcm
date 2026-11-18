<?php

namespace App\Filament\Resources\Patients\RelationManagers;

use App\Filament\Resources\MedicalConsultations\MedicalConsultationResource;
use App\Models\ClinicalOrder;
use Filament\Actions\Action;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class PatientClinicalOrdersRelationManager extends RelationManager
{
    protected static string $relationship = 'clinicalOrders';

    protected static bool $isLazy = false;

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('sigcm.clinical.orders_tab');
    }

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('title')
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['diagnosticReports']))
            ->columns([
                TextColumn::make('id')
                    ->label(__('sigcm.fields.order'))
                    ->formatStateUsing(fn ($state): string => "ORD-#{$state}")
                    ->sortable(),
                TextColumn::make('consultation_id')
                    ->label(__('sigcm.fields.consultation'))
                    ->formatStateUsing(fn ($state): string => "#{$state}")
                    ->url(fn (ClinicalOrder $record): string => MedicalConsultationResource::getUrl('view', ['record' => $record->consultation_id])),
                TextColumn::make('title')
                    ->label(__('sigcm.fields.order_title'))
                    ->searchable(),
                TextColumn::make('status')
                    ->label(__('sigcm.fields.status'))
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => __("enums.order_status.{$state}")),
                TextColumn::make('latest_report_status')
                    ->label(__('sigcm.fields.report_status'))
                    ->badge()
                    ->state(fn (ClinicalOrder $record): string => ($report = $record->diagnosticReports->sortByDesc('id')->first())
                        ? __("enums.report_status.{$report->status}")
                        : '-'),
            ])
            ->defaultSort('clinical_orders.id', 'desc')
            ->recordActions([
                Action::make('abrirConsulta')
                    ->label(__('sigcm.actions.view_detail'))
                    ->icon(Heroicon::OutlinedEye)
                    ->url(fn (ClinicalOrder $record): string => MedicalConsultationResource::getUrl('view', ['record' => $record->consultation_id])),
            ]);
    }
}
