<?php

namespace App\Filament\Resources\Appointments\Schemas;

use App\Enums\AppointmentStatus;
use App\Models\Doctor;
use App\Models\Patient;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class AppointmentForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Reserva de cita médica')
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema([
                        Select::make('patient_id')
                            ->label(__('sigcm.fields.patient'))
                            ->relationship('patient', 'first_names')
                            ->getOptionLabelFromRecordUsing(fn (Patient $record): string => $record->full_name)
                            ->searchable(['first_names', 'last_names', 'document_number'])
                            ->preload()
                            ->default(fn (): ?int => request()->integer('patient_id') ?: null)
                            ->required(),
                        Select::make('doctor_id')
                            ->label(__('sigcm.fields.doctor'))
                            ->relationship('doctor', 'first_names')
                            ->getOptionLabelFromRecordUsing(fn (Doctor $record): string => "{$record->full_name} ({$record->professional_license})")
                            ->searchable(['first_names', 'last_names', 'professional_license', 'specialty'])
                            ->preload()
                            ->required(),
                        DateTimePicker::make('starts_at')
                            ->label(__('sigcm.fields.starts_at'))
                            ->seconds(false)
                            ->required(),
                        DateTimePicker::make('ends_at')
                            ->label(__('sigcm.fields.ends_at'))
                            ->seconds(false)
                            ->after('starts_at')
                            ->required(),
                        Select::make('status')
                            ->label(__('sigcm.fields.status'))
                            ->options(AppointmentStatus::class)
                            ->default(AppointmentStatus::Pending)
                            ->live()
                            ->required(),
                        Select::make('created_by_user_id')
                            ->label(__('sigcm.fields.created_by'))
                            ->relationship('createdByUser', 'name')
                            ->disabled()
                            ->dehydrated(false)
                            ->visibleOn('edit'),
                        Textarea::make('reason')
                            ->label(__('sigcm.fields.appointment_reason'))
                            ->rows(2)
                            ->columnSpanFull(),
                        Textarea::make('cancellation_reason')
                            ->label(__('sigcm.fields.cancellation_reason'))
                            ->rows(2)
                            ->visible(fn (Get $get): bool => $get('status') === AppointmentStatus::Cancelled)
                            ->required(fn (Get $get): bool => $get('status') === AppointmentStatus::Cancelled)
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
