<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Filament\Resources\Doctors\DoctorResource;
use App\Models\User;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class UserInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('sigcm.users.sections.account'))
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema([
                        TextEntry::make('id')
                            ->label(__('sigcm.fields.id'))
                            ->numeric(),
                        IconEntry::make('is_active')
                            ->label(__('sigcm.fields.account_active'))
                            ->boolean(),
                        TextEntry::make('name')
                            ->label(__('sigcm.fields.name')),
                        TextEntry::make('email')
                            ->label(__('sigcm.fields.email')),
                        TextEntry::make('roles.name')
                            ->label(__('sigcm.fields.roles'))
                            ->badge()
                            ->color('primary')
                            ->placeholder('-'),
                        TextEntry::make('doctor.full_name')
                            ->label(__('sigcm.fields.linked_doctor'))
                            ->color('primary')
                            ->url(fn (User $record): ?string => $record->doctor !== null
                                ? DoctorResource::getUrl('view', ['record' => $record->doctor])
                                : null)
                            ->placeholder('-'),
                        TextEntry::make('created_at')
                            ->label(__('sigcm.fields.created_at'))
                            ->dateTime('d/m/Y H:i'),
                        TextEntry::make('updated_at')
                            ->label(__('sigcm.fields.date_time'))
                            ->dateTime('d/m/Y H:i'),
                    ]),
            ]);
    }
}
