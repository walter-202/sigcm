<?php

namespace App\Filament\Resources\Patients\Tables;

use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use pxlrbt\FilamentExcel\Actions\ExportAction;
use pxlrbt\FilamentExcel\Exports\ExcelExport;

class PatientsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label(__('sigcm.fields.id'))
                    ->numeric()
                    ->sortable(),
                TextColumn::make('full_name')
                    ->label(__('sigcm.fields.full_name'))
                    ->searchable(['first_names', 'last_names'])
                    ->sortable(['last_names', 'first_names']),
                TextColumn::make('document_type')
                    ->label(__('sigcm.fields.document_type_short'))
                    ->badge()
                    ->color('gray')
                    ->placeholder('-'),
                TextColumn::make('document_number')
                    ->label(__('sigcm.fields.document'))
                    ->searchable()
                    ->placeholder('-'),
                TextColumn::make('birth_date')
                    ->label(__('sigcm.fields.birth_date'))
                    ->date('d/m/Y')
                    ->sortable()
                    ->placeholder('-'),
                TextColumn::make('clinicalRecord.id')
                    ->label(__('sigcm.fields.clinical_record'))
                    ->badge()
                    ->formatStateUsing(fn ($state): string => $state ? 'HC-'.str_pad((string) $state, 4, '0', STR_PAD_LEFT) : 'Sin historia')
                    ->color(fn ($state): string => $state ? 'success' : 'gray')
                    ->placeholder('Sin historia'),
                TextColumn::make('phone')
                    ->label(__('sigcm.fields.phone'))
                    ->searchable()
                    ->toggleable(),
                IconColumn::make('is_active')
                    ->label(__('sigcm.fields.active'))
                    ->boolean(),
                TextColumn::make('created_at')
                    ->label(__('sigcm.fields.registered_at'))
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('id', 'desc')
            ->filters([
                TernaryFilter::make('is_active')
                    ->label(__('sigcm.fields.active')),
                SelectFilter::make('document_type')
                    ->label(__('sigcm.fields.document_type'))
                    ->options([
                        'CI' => 'CI',
                        'DNI' => 'DNI',
                        'PASAPORTE' => 'Pasaporte',
                        'CE' => 'Carnet de Extranjería',
                        'SIM' => 'SIM',
                    ]),
                TernaryFilter::make('has_clinical_record')
                    ->label(__('sigcm.fields.has_clinical_record'))
                    ->queries(
                        true: fn ($query) => $query->whereHas('clinicalRecord'),
                        false: fn ($query) => $query->whereDoesntHave('clinicalRecord'),
                    ),
            ])
            ->headerActions([
                ExportAction::make('exportarExcel')
                    ->label(__('sigcm.actions.export_excel'))
                    ->exports([
                        ExcelExport::make()
                            ->fromTable()
                            ->withFilename('pacientes-sigcm-'.date('Y-m-d')),
                    ]),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ]);
    }
}
