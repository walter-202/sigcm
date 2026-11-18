<?php

namespace App\Filament\Resources\MedicalConsultations\Tables;

use App\Enums\ConsultationStatus;
use App\Filament\Resources\MedicalConsultations\MedicalConsultationResource;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class MedicalConsultationsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label(__('sigcm.fields.id'))
                    ->numeric()
                    ->sortable(),
                TextColumn::make('patient.full_name')
                    ->label(__('sigcm.fields.patient'))
                    ->searchable(['first_names', 'last_names']),
                TextColumn::make('doctor.full_name')
                    ->label(__('sigcm.fields.doctor'))
                    ->searchable(['first_names', 'last_names']),
                TextColumn::make('chief_complaint')
                    ->label(__('sigcm.fields.chief_complaint'))
                    ->limit(45)
                    ->searchable(),
                TextColumn::make('occurred_at')
                    ->label(__('sigcm.fields.occurred_at'))
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
                TextColumn::make('status')
                    ->label(__('sigcm.fields.status'))
                    ->badge()
                    ->sortable(),
                TextColumn::make('signed_at')
                    ->label(__('sigcm.fields.signed_at'))
                    ->dateTime('d/m/Y H:i')
                    ->placeholder(__('enums.consultation_status.draft'))
                    ->sortable(),
            ])
            ->defaultSort('id', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->label(__('sigcm.fields.status'))
                    ->options(ConsultationStatus::class),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                MedicalConsultationResource::makeSignAction(),
            ]);
    }
}
