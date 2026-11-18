<?php

namespace App\Filament\Resources\Users\Tables;

use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label(__('sigcm.fields.id'))
                    ->numeric()
                    ->sortable(),
                TextColumn::make('name')
                    ->label(__('sigcm.fields.name'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('email')
                    ->label(__('sigcm.fields.email'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('roles.name')
                    ->label(__('sigcm.fields.roles'))
                    ->badge()
                    ->color('primary')
                    ->placeholder('-'),
                TextColumn::make('doctor.full_name')
                    ->label(__('sigcm.fields.linked_doctor'))
                    ->placeholder('-'),
                IconColumn::make('is_active')
                    ->label(__('sigcm.fields.account_active'))
                    ->boolean()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label(__('sigcm.fields.created_at'))
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->defaultSort('id')
            ->filters([
                TernaryFilter::make('is_active')
                    ->label(__('sigcm.fields.account_active')),
                SelectFilter::make('roles')
                    ->label(__('sigcm.fields.roles'))
                    ->relationship('roles', 'name'),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                Action::make('alternarAcceso')
                    ->label(fn (User $record): string => $record->is_active
                        ? __('sigcm.users.actions.deactivate')
                        : __('sigcm.users.actions.activate'))
                    ->icon(fn (User $record): Heroicon => $record->is_active
                        ? Heroicon::OutlinedLockClosed
                        : Heroicon::OutlinedLockOpen)
                    ->color(fn (User $record): string => $record->is_active ? 'warning' : 'success')
                    ->requiresConfirmation()
                    ->visible(fn (User $record): bool => $record->id !== Auth::id() && (Auth::user()?->can('update', $record) ?? false))
                    ->action(function (User $record): void {
                        $nextState = ! $record->is_active;
                        $record->update(['is_active' => $nextState]);

                        Notification::make()
                            ->title($nextState
                                ? __('sigcm.users.actions.activated_ok')
                                : __('sigcm.users.actions.deactivated_ok'))
                            ->success()
                            ->send();
                    }),
                Action::make('restablecerContrasena')
                    ->label(__('sigcm.users.actions.reset_password'))
                    ->icon(Heroicon::OutlinedKey)
                    ->color('gray')
                    ->visible(fn (User $record): bool => UserResource::canEdit($record))
                    ->schema([
                        TextInput::make('password')
                            ->label(__('sigcm.fields.new_password'))
                            ->password()
                            ->revealable()
                            ->confirmed()
                            ->minLength(8)
                            ->required(),
                        TextInput::make('password_confirmation')
                            ->label(__('sigcm.fields.password_confirmation'))
                            ->password()
                            ->revealable()
                            ->required(),
                    ])
                    ->action(function (User $record, array $data): void {
                        $record->update(['password' => $data['password']]);

                        Notification::make()
                            ->title(__('sigcm.users.actions.password_reset_ok'))
                            ->success()
                            ->send();
                    }),
            ]);
    }
}
