<?php

namespace App\Filament\Resources\ClinicalRecords\Tables;

use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ClinicalRecordsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label(__('sigcm.fields.clinical_record'))
                    ->formatStateUsing(fn ($state): string => 'HC-'.str_pad((string) $state, 4, '0', STR_PAD_LEFT))
                    ->sortable(),
                TextColumn::make('patient.full_name')
                    ->label(__('sigcm.fields.patient'))
                    ->searchable(['first_names', 'last_names']),
                TextColumn::make('opened_from_consultation_id')
                    ->label(__('sigcm.fields.opened_from_consultation'))
                    ->badge()
                    ->formatStateUsing(fn ($state): string => "#{$state}")
                    ->color('success')
                    ->sortable(),
                TextColumn::make('care_episodes_count')
                    ->label(__('sigcm.fields.episode'))
                    ->counts('careEpisodes')
                    ->sortable(),
                TextColumn::make('opened_at')
                    ->label(__('sigcm.fields.opened_at'))
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->defaultSort('id', 'desc')
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ]);
    }
}
