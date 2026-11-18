<?php

namespace App\Filament\Resources\Patients\RelationManagers;

use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class ConditionsRelationManager extends RelationManager
{
    protected static string $relationship = 'conditions';

    protected static bool $isLazy = false;

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('sigcm.patients.conditions_tab');
    }

    public function isReadOnly(): bool
    {
        return ! (auth()->user()?->can('update', $this->getOwnerRecord()) ?? false);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('code_system')
                    ->label(__('sigcm.fields.code_system'))
                    ->default('CIE-10')
                    ->maxLength(70),
                TextInput::make('code')
                    ->label(__('sigcm.fields.code'))
                    ->maxLength(70),
                TextInput::make('display')
                    ->label(__('sigcm.fields.display'))
                    ->required()
                    ->maxLength(255)
                    ->columnSpanFull(),
                Select::make('clinical_status')
                    ->label(__('sigcm.fields.clinical_status'))
                    ->options([
                        'active' => 'Activa',
                        'resolved' => 'Resuelta',
                        'inactive' => 'Inactiva',
                    ])
                    ->default('active')
                    ->required(),
                Select::make('verification_status')
                    ->label(__('sigcm.fields.verification_status'))
                    ->options([
                        'unconfirmed' => 'Presuntiva',
                        'confirmed' => 'Confirmada',
                        'refuted' => 'Descartada',
                    ])
                    ->default('confirmed')
                    ->required(),
                DatePicker::make('onset_date')
                    ->label(__('sigcm.fields.onset_date')),
                DatePicker::make('resolved_date')
                    ->label(__('sigcm.fields.resolved_date'))
                    ->afterOrEqual('onset_date'),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('display')
            ->columns([
                TextColumn::make('code')
                    ->label(__('sigcm.fields.code'))
                    ->badge()
                    ->formatStateUsing(fn (?string $state, Model $record): string => $state ? "{$record->code_system}: {$state}" : 'Sin código'),
                TextColumn::make('display')
                    ->label(__('sigcm.fields.display'))
                    ->searchable(),
                TextColumn::make('clinical_status')
                    ->label(__('sigcm.fields.status'))
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'active' => 'Activa',
                        'resolved' => 'Resuelta',
                        default => 'Inactiva',
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'active' => 'warning',
                        'resolved' => 'success',
                        default => 'gray',
                    }),
                TextColumn::make('verification_status')
                    ->label(__('sigcm.fields.certainty'))
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'confirmed' => 'Confirmada',
                        'refuted' => 'Descartada',
                        default => 'Presuntiva',
                    }),
                TextColumn::make('onset_date')
                    ->label(__('sigcm.fields.onset_date'))
                    ->date('d/m/Y')
                    ->placeholder('-'),
            ])
            ->defaultSort('id', 'desc')
            ->headerActions([
                CreateAction::make()
                    ->label(__('sigcm.actions.register_condition'))
                    ->mutateDataUsing(fn (array $data): array => [
                        ...$data,
                        'created_at' => now(),
                    ]),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }
}
