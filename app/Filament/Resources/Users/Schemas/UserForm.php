<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Models\Doctor;
use App\Models\User;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('sigcm.users.sections.account'))
                    ->description(__('sigcm.users.sections.account_desc'))
                    ->columnSpanFull()
                    ->schema([
                        Grid::make(12)
                            ->schema([
                                TextInput::make('name')
                                    ->label(__('sigcm.fields.name'))
                                    ->required()
                                    ->maxLength(255)
                                    ->columnSpan(['default' => 12, 'md' => 6]),
                                TextInput::make('email')
                                    ->label(__('sigcm.fields.email'))
                                    ->email()
                                    ->required()
                                    ->unique(ignoreRecord: true)
                                    ->maxLength(255)
                                    ->columnSpan(['default' => 12, 'md' => 6]),
                                Select::make('roles')
                                    ->label(__('sigcm.fields.roles'))
                                    ->relationship('roles', 'name')
                                    ->multiple()
                                    ->preload()
                                    ->searchable()
                                    ->required()
                                    ->columnSpan(['default' => 12, 'md' => 8]),
                                Toggle::make('is_active')
                                    ->label(__('sigcm.fields.account_active'))
                                    ->helperText(__('sigcm.users.helpers.active_panel'))
                                    ->default(true)
                                    ->inline(false)
                                    ->required()
                                    ->columnSpan(['default' => 12, 'md' => 4]),
                            ]),
                    ]),

                Section::make(__('sigcm.users.sections.credentials'))
                    ->description(__('sigcm.users.sections.credentials_desc'))
                    ->columnSpanFull()
                    ->schema([
                        Grid::make(12)
                            ->schema([
                                TextInput::make('password')
                                    ->label(__('sigcm.fields.password'))
                                    ->password()
                                    ->revealable()
                                    ->confirmed()
                                    ->minLength(8)
                                    ->required(fn (string $operation): bool => $operation === 'create')
                                    ->helperText(fn (string $operation): ?string => $operation === 'edit' ? __('sigcm.users.helpers.password_edit') : null)
                                    ->dehydrated(fn (?string $state): bool => filled($state))
                                    ->columnSpan(['default' => 12, 'md' => 6]),
                                TextInput::make('password_confirmation')
                                    ->label(__('sigcm.fields.password_confirmation'))
                                    ->password()
                                    ->revealable()
                                    ->required(fn (string $operation, Get $get): bool => $operation === 'create' || filled($get('password')))
                                    ->dehydrated(false)
                                    ->columnSpan(['default' => 12, 'md' => 6]),
                            ]),
                    ]),

                Section::make(__('sigcm.users.sections.doctor_link'))
                    ->description(__('sigcm.users.sections.doctor_link_desc'))
                    ->columnSpanFull()
                    ->schema([
                        Select::make('linked_doctor_id')
                            ->label(__('sigcm.fields.linked_doctor'))
                            ->helperText(__('sigcm.users.helpers.doctor_link'))
                            ->options(fn (?User $record): array => Doctor::query()
                                ->where(function ($query) use ($record): void {
                                    $query->whereNull('user_id');
                                    if ($record !== null) {
                                        $query->orWhere('user_id', $record->id);
                                    }
                                })
                                ->orderBy('last_names')
                                ->get()
                                ->mapWithKeys(fn (Doctor $doctor): array => [
                                    $doctor->id => "{$doctor->full_name} · {$doctor->professional_license}",
                                ])
                                ->all())
                            ->default(fn (?User $record): ?int => $record?->doctor?->id)
                            ->afterStateHydrated(function (Select $component, ?User $record): void {
                                if ($record !== null && blank($component->getState())) {
                                    $component->state($record->doctor?->id);
                                }
                            })
                            ->searchable()
                            ->nullable()
                            ->dehydrated(false)
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
