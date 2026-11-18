<?php

namespace App\Filament\Resources\ClinicalRecords\Schemas;

use App\Models\Patient;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ClinicalRecordForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Apertura del expediente longitudinal (Solo lectura)')
                    ->columns(3)
                    ->columnSpanFull()
                    ->schema([
                        Select::make('patient_id')
                            ->label(__('sigcm.fields.patient'))
                            ->relationship('patient', 'first_names')
                            ->getOptionLabelFromRecordUsing(fn (Patient $record): string => $record->full_name)
                            ->disabled()
                            ->dehydrated(false),
                        TextInput::make('opened_from_consultation_id')
                            ->label(__('sigcm.fields.opened_from_consultation'))
                            ->prefix('#')
                            ->disabled()
                            ->dehydrated(false),
                        DateTimePicker::make('opened_at')
                            ->label(__('sigcm.fields.opened_at'))
                            ->disabled()
                            ->dehydrated(false),
                    ]),

                Section::make('Antecedentes longitudinales del paciente')
                    ->columnSpanFull()
                    ->schema([
                        Textarea::make('medical_history')
                            ->label(__('sigcm.fields.medical_history'))
                            ->rows(3)
                            ->columnSpanFull(),
                        Textarea::make('surgical_history')
                            ->label(__('sigcm.fields.surgical_history'))
                            ->rows(3)
                            ->columnSpanFull(),
                        Textarea::make('family_history')
                            ->label(__('sigcm.fields.family_history'))
                            ->rows(3)
                            ->columnSpanFull(),
                        Textarea::make('social_history')
                            ->label(__('sigcm.fields.social_history'))
                            ->rows(3)
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
