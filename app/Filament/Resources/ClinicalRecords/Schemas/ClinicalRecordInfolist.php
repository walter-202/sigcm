<?php

namespace App\Filament\Resources\ClinicalRecords\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ClinicalRecordInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Expediente clínico')
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema([
                        TextEntry::make('id')
                            ->label(__('sigcm.fields.clinical_record'))
                            ->numeric(),
                        TextEntry::make('patient.full_name')
                            ->label(__('sigcm.fields.patient')),
                        TextEntry::make('opened_from_consultation_id')
                            ->label(__('sigcm.fields.opened_from_consultation'))
                            ->badge()
                            ->formatStateUsing(fn ($state): string => "Consulta #{$state}")
                            ->color('success'),
                        TextEntry::make('opened_at')
                            ->label(__('sigcm.fields.opened_at'))
                            ->dateTime('d/m/Y H:i'),
                    ]),

                Section::make('Antecedentes longitudinales')
                    ->columnSpanFull()
                    ->schema([
                        TextEntry::make('medical_history')
                            ->label(__('sigcm.fields.medical_history'))
                            ->placeholder('Sin registros')
                            ->columnSpanFull(),
                        TextEntry::make('surgical_history')
                            ->label(__('sigcm.fields.surgical_history'))
                            ->placeholder('Sin registros')
                            ->columnSpanFull(),
                        TextEntry::make('family_history')
                            ->label(__('sigcm.fields.family_history'))
                            ->placeholder('Sin registros')
                            ->columnSpanFull(),
                        TextEntry::make('social_history')
                            ->label(__('sigcm.fields.social_history'))
                            ->placeholder('Sin registros')
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
