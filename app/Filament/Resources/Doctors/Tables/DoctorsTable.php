<?php

namespace App\Filament\Resources\Doctors\Tables;

use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class DoctorsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label(__('sigcm.fields.id'))
                    ->numeric()
                    ->sortable(),
                TextColumn::make('professional_license')
                    ->label(__('sigcm.fields.professional_license'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('full_name')
                    ->label(__('sigcm.fields.doctor'))
                    ->searchable(['first_names', 'last_names'])
                    ->sortable(['last_names', 'first_names']),
                TextColumn::make('specialty')
                    ->label(__('sigcm.fields.specialty'))
                    ->badge()
                    ->color('info')
                    ->placeholder('Medicina general')
                    ->searchable(),
                TextColumn::make('user.email')
                    ->label(__('sigcm.fields.actor'))
                    ->placeholder('Sin cuenta')
                    ->searchable(),
                TextColumn::make('appointments_count')
                    ->label(__('sigcm.nav.appointments'))
                    ->counts('appointments')
                    ->sortable(),
                IconColumn::make('is_active')
                    ->label(__('sigcm.fields.active'))
                    ->boolean(),
            ])
            ->defaultSort('id', 'asc')
            ->filters([
                TernaryFilter::make('is_active')
                    ->label(__('sigcm.fields.active')),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ]);
    }
}
