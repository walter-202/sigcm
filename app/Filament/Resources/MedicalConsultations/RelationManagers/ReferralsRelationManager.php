<?php

namespace App\Filament\Resources\MedicalConsultations\RelationManagers;

use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class ReferralsRelationManager extends RelationManager
{
    protected static string $relationship = 'referrals';

    protected static bool $isLazy = false;

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('sigcm.clinical.referrals_tab');
    }

    public function isReadOnly(): bool
    {
        return ! (Auth::user()?->can('create', $this->getOwnerRecord()::class) ?? false);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('specialty_or_service')
                    ->label(__('sigcm.fields.specialty_or_service'))
                    ->required()
                    ->maxLength(180),
                Select::make('status')
                    ->label(__('sigcm.fields.status'))
                    ->options([
                        'requested' => 'Solicitada',
                        'accepted' => 'Aceptada',
                        'completed' => 'Completada',
                        'cancelled' => 'Cancelada',
                    ])
                    ->default('requested')
                    ->required(),
                Textarea::make('reason')
                    ->label(__('sigcm.fields.referral_reason'))
                    ->required()
                    ->rows(3)
                    ->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('specialty_or_service')
            ->columns([
                TextColumn::make('id')
                    ->label(__('sigcm.fields.id'))
                    ->formatStateUsing(fn ($state): string => "INT-#{$state}")
                    ->sortable(),
                TextColumn::make('specialty_or_service')
                    ->label(__('sigcm.fields.specialty_or_service'))
                    ->searchable(),
                TextColumn::make('reason')
                    ->label(__('sigcm.fields.reason'))
                    ->limit(50),
                TextColumn::make('status')
                    ->label(__('sigcm.fields.status'))
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'accepted' => 'Aceptada',
                        'completed' => 'Completada',
                        'cancelled' => 'Cancelada',
                        default => 'Solicitada',
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'completed' => 'success',
                        'accepted' => 'info',
                        'cancelled' => 'danger',
                        default => 'warning',
                    }),
                TextColumn::make('requested_at')
                    ->label(__('sigcm.fields.requested_at'))
                    ->dateTime('d/m/Y H:i'),
            ])
            ->defaultSort('id', 'desc')
            ->headerActions([
                CreateAction::make()
                    ->label(__('sigcm.actions.new_referral'))
                    ->mutateDataUsing(fn (array $data): array => [
                        ...$data,
                        'requested_at' => now(),
                    ]),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }
}
