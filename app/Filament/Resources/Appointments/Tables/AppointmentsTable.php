<?php

namespace App\Filament\Resources\Appointments\Tables;

use App\Enums\AppointmentStatus;
use App\Filament\Resources\Appointments\AppointmentResource;
use App\Filament\Resources\MedicalConsultations\MedicalConsultationResource;
use App\Models\Appointment;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use pxlrbt\FilamentExcel\Actions\ExportAction;
use pxlrbt\FilamentExcel\Exports\ExcelExport;

class AppointmentsTable
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
                TextColumn::make('starts_at')
                    ->label(__('sigcm.fields.starts_at'))
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
                TextColumn::make('ends_at')
                    ->label(__('sigcm.fields.ends_at'))
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
                TextColumn::make('status')
                    ->label(__('sigcm.fields.status'))
                    ->badge()
                    ->sortable(),
                TextColumn::make('medicalConsultation.id')
                    ->label(__('sigcm.fields.consultation'))
                    ->badge()
                    ->formatStateUsing(fn ($state): string => $state ? "Consulta #{$state}" : 'Sin consulta')
                    ->color(fn ($state): string => $state ? 'success' : 'gray')
                    ->url(fn (Appointment $record): ?string => $record->medicalConsultation !== null
                        ? MedicalConsultationResource::getUrl('view', ['record' => $record->medicalConsultation])
                        : null)
                    ->placeholder('Sin consulta'),
            ])
            ->defaultSort('starts_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->label(__('sigcm.fields.status'))
                    ->options(AppointmentStatus::class),
                SelectFilter::make('doctor_id')
                    ->label(__('sigcm.fields.doctor'))
                    ->relationship('doctor', 'last_names'),
            ])
            ->headerActions([
                ExportAction::make('exportarCitas')
                    ->label(__('sigcm.actions.export_excel'))
                    ->exports([
                        ExcelExport::make()
                            ->fromTable()
                            ->withFilename('citas-sigcm-'.date('Y-m-d')),
                    ]),
            ])
            ->recordActions([
                AppointmentResource::makeAttendAction(),
                AppointmentResource::makeViewConsultationAction(),
                ViewAction::make(),
                EditAction::make(),
                AppointmentResource::makeRescheduleAction(),
            ]);
    }
}
