<?php

namespace App\Filament\Resources\Doctors\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class DoctorInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Perfil del profesional')
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema([
                        TextEntry::make('id')
                            ->label(__('sigcm.fields.id'))
                            ->numeric(),
                        TextEntry::make('full_name')
                            ->label(__('sigcm.fields.full_name')),
                        TextEntry::make('professional_license')
                            ->label(__('sigcm.fields.professional_license'))
                            ->badge()
                            ->color('primary'),
                        TextEntry::make('specialty')
                            ->label(__('sigcm.fields.specialty'))
                            ->placeholder('Medicina general'),
                        TextEntry::make('user.email')
                            ->label(__('sigcm.fields.system_account'))
                            ->placeholder('Sin usuario vinculado'),
                        IconEntry::make('is_active')
                            ->label(__('sigcm.fields.active'))
                            ->boolean(),
                        TextEntry::make('created_at')
                            ->label(__('sigcm.fields.registered_at'))
                            ->dateTime('d/m/Y H:i'),
                        TextEntry::make('updated_at')
                            ->label(__('sigcm.fields.date_time'))
                            ->dateTime('d/m/Y H:i'),
                    ]),
            ]);
    }
}
