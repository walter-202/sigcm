<?php

namespace App\Filament\Resources\Patients\Schemas;

use Carbon\Carbon;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Validation\Rules\Unique;
use Throwable;

class PatientForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('sigcm.patients.form.identification_section'))
                    ->description(__('sigcm.patients.form.identification_desc'))
                    ->columnSpanFull()
                    ->schema([
                        Grid::make(12)
                            ->schema([
                                TextInput::make('first_names')
                                    ->label(__('sigcm.fields.first_names'))
                                    ->placeholder(__('sigcm.patients.form.first_names_placeholder'))
                                    ->required()
                                    ->maxLength(150)
                                    ->columnSpan(['default' => 12, 'md' => 6]),
                                TextInput::make('last_names')
                                    ->label(__('sigcm.fields.last_names'))
                                    ->placeholder(__('sigcm.patients.form.last_names_placeholder'))
                                    ->required()
                                    ->maxLength(150)
                                    ->columnSpan(['default' => 12, 'md' => 6]),
                                Select::make('document_type')
                                    ->label(__('sigcm.fields.document_type'))
                                    ->options([
                                        'CI' => 'CI',
                                        'DNI' => 'DNI',
                                        'PASAPORTE' => 'Pasaporte',
                                        'CE' => 'Carnet de Extranjería',
                                        'SIM' => 'SIM',
                                    ])
                                    ->searchable()
                                    ->requiredWith('document_number')
                                    ->columnSpan(['default' => 12, 'md' => 4]),
                                TextInput::make('document_number')
                                    ->label(__('sigcm.fields.document_number'))
                                    ->placeholder(__('sigcm.patients.form.document_number_placeholder'))
                                    ->helperText(__('sigcm.patients.form.document_helper'))
                                    ->requiredWith('document_type')
                                    ->unique(
                                        ignoreRecord: true,
                                        modifyRuleUsing: fn (Unique $rule, Get $get): Unique => $rule->where('document_type', $get('document_type'))
                                    )
                                    ->maxLength(60)
                                    ->columnSpan(['default' => 12, 'md' => 4]),
                                DatePicker::make('birth_date')
                                    ->label(__('sigcm.fields.birth_date'))
                                    ->maxDate(now())
                                    ->live()
                                    ->helperText(function (?string $state): string {
                                        if (blank($state)) {
                                            return __('sigcm.patients.form.birth_date_helper');
                                        }

                                        try {
                                            $age = Carbon::parse($state)->age;

                                            return __('sigcm.patients.form.birth_date_age_helper', ['age' => $age]);
                                        } catch (Throwable) {
                                            return __('sigcm.patients.form.birth_date_helper');
                                        }
                                    })
                                    ->columnSpan(['default' => 12, 'md' => 4]),
                            ]),
                    ]),

                Section::make(__('sigcm.patients.form.contact_section'))
                    ->description(__('sigcm.patients.form.contact_desc'))
                    ->columnSpanFull()
                    ->schema([
                        Grid::make(12)
                            ->schema([
                                TextInput::make('phone')
                                    ->label(__('sigcm.fields.phone'))
                                    ->placeholder(__('sigcm.patients.form.phone_placeholder'))
                                    ->tel()
                                    ->maxLength(40)
                                    ->columnSpan(['default' => 12, 'md' => 6]),
                                TextInput::make('email')
                                    ->label(__('sigcm.fields.email'))
                                    ->placeholder(__('sigcm.patients.form.email_placeholder'))
                                    ->email()
                                    ->maxLength(255)
                                    ->columnSpan(['default' => 12, 'md' => 6]),
                            ]),
                    ]),

                Section::make(__('sigcm.patients.form.status_section'))
                    ->description(__('sigcm.patients.form.status_desc'))
                    ->columnSpanFull()
                    ->schema([
                        Toggle::make('is_active')
                            ->label(__('sigcm.fields.is_active'))
                            ->helperText(__('sigcm.patients.form.active_helper'))
                            ->default(true)
                            ->inline(false)
                            ->required(),
                    ]),
            ]);
    }
}
