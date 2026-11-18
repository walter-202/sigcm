<?php

namespace App\Filament\Resources\Appointments\RelationManagers;

use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ReschedulesRelationManager extends RelationManager
{
    protected static string $relationship = 'reschedules';

    protected static bool $isLazy = false;

    protected static ?string $title = 'Historial de reprogramaciones';

    public function isReadOnly(): bool
    {
        return true;
    }

    public function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('id')
                    ->label(__('sigcm.fields.id'))
                    ->numeric(),
                TextEntry::make('changedByUser.name')
                    ->label(__('sigcm.fields.changed_by')),
                TextEntry::make('previous_start')
                    ->label(__('sigcm.fields.previous_start'))
                    ->dateTime('d/m/Y H:i'),
                TextEntry::make('previous_end')
                    ->label(__('sigcm.fields.previous_end'))
                    ->dateTime('d/m/Y H:i'),
                TextEntry::make('new_start')
                    ->label(__('sigcm.fields.new_start'))
                    ->dateTime('d/m/Y H:i'),
                TextEntry::make('new_end')
                    ->label(__('sigcm.fields.new_end'))
                    ->dateTime('d/m/Y H:i'),
                TextEntry::make('reason')
                    ->label(__('sigcm.fields.reason'))
                    ->columnSpanFull(),
                TextEntry::make('created_at')
                    ->label(__('sigcm.fields.registered_at'))
                    ->dateTime('d/m/Y H:i'),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('reason')
            ->columns([
                TextColumn::make('id')
                    ->label(__('sigcm.fields.id'))
                    ->numeric()
                    ->sortable(),
                TextColumn::make('changedByUser.name')
                    ->label(__('sigcm.fields.changed_by'))
                    ->searchable(),
                TextColumn::make('previous_start')
                    ->label(__('sigcm.fields.previous_start'))
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
                TextColumn::make('new_start')
                    ->label(__('sigcm.fields.new_start'))
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
                TextColumn::make('reason')
                    ->label(__('sigcm.fields.reason'))
                    ->limit(50),
                TextColumn::make('created_at')
                    ->label(__('sigcm.fields.date'))
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->defaultSort('id', 'desc')
            ->recordActions([
                ViewAction::make(),
            ]);
    }
}
