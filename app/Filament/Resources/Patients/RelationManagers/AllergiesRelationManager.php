<?php

namespace App\Filament\Resources\Patients\RelationManagers;

use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class AllergiesRelationManager extends RelationManager
{
    protected static string $relationship = 'allergies';

    protected static bool $isLazy = false;

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('sigcm.patients.allergies_tab');
    }

    public function isReadOnly(): bool
    {
        return ! (auth()->user()?->can('update', $this->getOwnerRecord()) ?? false);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('substance')
                    ->label(__('sigcm.fields.substance'))
                    ->required()
                    ->maxLength(180),
                TextInput::make('reaction')
                    ->label(__('sigcm.fields.reaction'))
                    ->maxLength(180),
                Select::make('severity')
                    ->label(__('sigcm.fields.severity'))
                    ->options([
                        'mild' => __('enums.allergy_severity.mild'),
                        'moderate' => __('enums.allergy_severity.moderate'),
                        'severe' => __('enums.allergy_severity.severe'),
                    ])
                    ->nullable(),
                Select::make('verification_status')
                    ->label(__('sigcm.fields.verification_status'))
                    ->options([
                        'unconfirmed' => 'Por confirmar',
                        'confirmed' => 'Confirmada',
                        'refuted' => 'Descartada',
                    ])
                    ->default('confirmed')
                    ->required(),
                Select::make('clinical_status')
                    ->label(__('sigcm.fields.clinical_status'))
                    ->options([
                        'active' => 'Activa',
                        'inactive' => 'Inactiva',
                        'resolved' => 'Resuelta',
                    ])
                    ->default('active')
                    ->required(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('substance')
            ->columns([
                TextColumn::make('substance')
                    ->label(__('sigcm.fields.substance'))
                    ->searchable(),
                TextColumn::make('reaction')
                    ->label(__('sigcm.fields.reaction'))
                    ->placeholder('-'),
                TextColumn::make('severity')
                    ->label(__('sigcm.fields.severity'))
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => $state ? __("enums.allergy_severity.{$state}") : 'Sin definir')
                    ->color(fn (?string $state): string => match ($state) {
                        'severe' => 'danger',
                        'moderate' => 'warning',
                        'mild' => 'info',
                        default => 'gray',
                    }),
                TextColumn::make('verification_status')
                    ->label(__('sigcm.fields.verification_status'))
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'confirmed' => 'Confirmada',
                        'refuted' => 'Descartada',
                        default => 'Por confirmar',
                    })
                    ->color(fn (string $state): string => $state === 'confirmed' ? 'success' : 'gray'),
                TextColumn::make('clinical_status')
                    ->label(__('sigcm.fields.status'))
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'active' => 'Activa',
                        'resolved' => 'Resuelta',
                        default => 'Inactiva',
                    })
                    ->color(fn (string $state): string => $state === 'active' ? 'danger' : 'gray'),
            ])
            ->defaultSort('id', 'desc')
            ->headerActions([
                CreateAction::make()
                    ->label(__('sigcm.actions.register_allergy'))
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
