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

class MedicationsRelationManager extends RelationManager
{
    protected static string $relationship = 'medications';

    protected static bool $isLazy = false;

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('sigcm.patients.medications_tab');
    }

    public function isReadOnly(): bool
    {
        return ! (auth()->user()?->can('update', $this->getOwnerRecord()) ?? false);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('medication_name')
                    ->label(__('sigcm.fields.medication_name'))
                    ->required()
                    ->maxLength(180)
                    ->columnSpanFull(),
                TextInput::make('dose')
                    ->label(__('sigcm.fields.dose'))
                    ->maxLength(100),
                TextInput::make('frequency')
                    ->label(__('sigcm.fields.frequency'))
                    ->maxLength(100),
                Select::make('status')
                    ->label(__('sigcm.fields.status'))
                    ->options([
                        'active' => 'Activo',
                        'stopped' => 'Suspendido',
                        'unknown' => 'Sin confirmar',
                    ])
                    ->default('active')
                    ->required(),
                Select::make('source')
                    ->label(__('sigcm.fields.source'))
                    ->options([
                        'patient_report' => 'Referido por paciente',
                        'external_prescription' => 'Receta externa',
                        'internal_reconciliation' => 'Conciliación clínica',
                    ])
                    ->default('patient_report')
                    ->required(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('medication_name')
            ->columns([
                TextColumn::make('medication_name')
                    ->label(__('sigcm.fields.medication_name'))
                    ->searchable(),
                TextColumn::make('dose')
                    ->label(__('sigcm.fields.dose'))
                    ->placeholder('-'),
                TextColumn::make('frequency')
                    ->label(__('sigcm.fields.frequency'))
                    ->placeholder('-'),
                TextColumn::make('status')
                    ->label(__('sigcm.fields.status'))
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'active' => 'Activo',
                        'stopped' => 'Suspendido',
                        default => 'Sin confirmar',
                    })
                    ->color(fn (string $state): string => $state === 'active' ? 'success' : 'gray'),
                TextColumn::make('source')
                    ->label(__('sigcm.fields.source'))
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'external_prescription' => 'Receta externa',
                        'internal_reconciliation' => 'Conciliación clínica',
                        default => 'Referido por paciente',
                    }),
                TextColumn::make('recorded_at')
                    ->label(__('sigcm.fields.recorded_at'))
                    ->dateTime('d/m/Y H:i'),
            ])
            ->defaultSort('id', 'desc')
            ->headerActions([
                CreateAction::make()
                    ->label(__('sigcm.actions.register_medication'))
                    ->mutateDataUsing(fn (array $data): array => [
                        ...$data,
                        'recorded_at' => now(),
                    ]),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }
}
