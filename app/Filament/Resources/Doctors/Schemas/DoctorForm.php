<?php

namespace App\Filament\Resources\Doctors\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class DoctorForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Información profesional')
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema([
                        TextInput::make('first_names')
                            ->label(__('sigcm.fields.first_names'))
                            ->required()
                            ->maxLength(150),
                        TextInput::make('last_names')
                            ->label(__('sigcm.fields.last_names'))
                            ->required()
                            ->maxLength(150),
                        TextInput::make('professional_license')
                            ->label(__('sigcm.fields.professional_license'))
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(70),
                        TextInput::make('specialty')
                            ->label(__('sigcm.fields.specialty'))
                            ->maxLength(150),
                        Select::make('user_id')
                            ->label(__('sigcm.fields.linked_user'))
                            ->relationship('user', 'name')
                            ->searchable()
                            ->preload()
                            ->unique(ignoreRecord: true)
                            ->nullable(),
                        Toggle::make('is_active')
                            ->label(__('sigcm.fields.active'))
                            ->default(true)
                            ->inline(false)
                            ->required(),
                    ]),
            ]);
    }
}
