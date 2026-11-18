<?php

namespace App\Filament\Resources\MedicalConsultations\RelationManagers;

use App\Models\Prescription;
use App\Services\SigcmDatabaseService;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Throwable;

class PrescriptionsRelationManager extends RelationManager
{
    protected static string $relationship = 'prescriptions';

    protected static bool $isLazy = false;

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('sigcm.clinical.prescriptions_tab');
    }

    public function isReadOnly(): bool
    {
        return ! (Auth::user()?->can('create', Prescription::class) ?? false);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Textarea::make('notes')
                    ->label(__('sigcm.fields.notes'))
                    ->rows(2)
                    ->columnSpanFull(),
                Repeater::make('items')
                    ->label(__('sigcm.fields.items'))
                    ->relationship('items')
                    ->defaultItems(1)
                    ->minItems(1)
                    ->columns(2)
                    ->schema([
                        TextInput::make('medication_name')
                            ->label(__('sigcm.fields.medication_name'))
                            ->required()
                            ->maxLength(180),
                        TextInput::make('dose')
                            ->label(__('sigcm.fields.dose'))
                            ->required()
                            ->maxLength(100),
                        TextInput::make('route')
                            ->label(__('sigcm.fields.route'))
                            ->default('Vía oral')
                            ->required()
                            ->maxLength(100),
                        TextInput::make('frequency')
                            ->label(__('sigcm.fields.frequency'))
                            ->required()
                            ->maxLength(100),
                        TextInput::make('duration_days')
                            ->label(__('sigcm.fields.duration_days'))
                            ->numeric()
                            ->minValue(1),
                        TextInput::make('quantity')
                            ->label(__('sigcm.fields.quantity'))
                            ->numeric()
                            ->minValue(0.01)
                            ->step('0.01'),
                        Textarea::make('instructions')
                            ->label(__('sigcm.fields.instructions'))
                            ->rows(2)
                            ->columnSpanFull(),
                    ])
                    ->mutateRelationshipDataBeforeCreateUsing(fn (array $data): array => [
                        ...$data,
                        'created_at' => now(),
                    ])
                    ->columnSpanFull(),
            ]);
    }

    public function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('id')
                    ->label(__('sigcm.fields.prescription'))
                    ->formatStateUsing(fn ($state): string => "REC-#{$state}"),
                TextEntry::make('status')
                    ->label(__('sigcm.fields.status'))
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => __("enums.prescription_status.{$state}")),
                TextEntry::make('issued_at')
                    ->label(__('sigcm.fields.issued_at'))
                    ->dateTime('d/m/Y H:i')
                    ->placeholder(__('enums.prescription_status.draft')),
                TextEntry::make('supersedes_prescription_id')
                    ->label(__('sigcm.fields.supersedes_prescription'))
                    ->formatStateUsing(fn ($state): string => $state ? "REC-#{$state}" : '-')
                    ->placeholder('-'),
                TextEntry::make('notes')
                    ->label(__('sigcm.fields.notes'))
                    ->placeholder('-')
                    ->columnSpanFull(),
                RepeatableEntry::make('items')
                    ->label(__('sigcm.fields.items'))
                    ->columns(4)
                    ->schema([
                        TextEntry::make('medication_name')->label(__('sigcm.fields.medication_name')),
                        TextEntry::make('dose')->label(__('sigcm.fields.dose')),
                        TextEntry::make('frequency')->label(__('sigcm.fields.frequency')),
                        TextEntry::make('route')->label(__('sigcm.fields.route')),
                    ])
                    ->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['items'])->withCount('items'))
            ->columns([
                TextColumn::make('id')
                    ->label(__('sigcm.fields.prescription'))
                    ->formatStateUsing(fn ($state): string => "REC-#{$state}")
                    ->sortable(),
                TextColumn::make('status')
                    ->label(__('sigcm.fields.status'))
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => __("enums.prescription_status.{$state}"))
                    ->color(fn (string $state): string => match ($state) {
                        'issued' => 'success',
                        'replaced' => 'warning',
                        'void' => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('items_count')
                    ->label(__('sigcm.fields.items_count'))
                    ->badge()
                    ->color('info'),
                TextColumn::make('issued_at')
                    ->label(__('sigcm.fields.issued_at'))
                    ->dateTime('d/m/Y H:i')
                    ->placeholder(__('enums.prescription_status.draft')),
                TextColumn::make('notes')
                    ->label(__('sigcm.fields.notes'))
                    ->limit(40)
                    ->placeholder('-'),
            ])
            ->defaultSort('id', 'desc')
            ->headerActions([
                CreateAction::make()
                    ->label(__('sigcm.actions.new_prescription'))
                    ->mutateDataUsing(fn (array $data): array => [
                        ...$data,
                        'status' => 'draft',
                        'issued_at' => null,
                        'created_at' => now(),
                    ]),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make()
                    ->visible(fn (Prescription $record): bool => $record->status === 'draft'),
                Action::make('emitir')
                    ->label(__('sigcm.clinical.issue_prescription'))
                    ->icon(Heroicon::OutlinedCheckCircle)
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (Prescription $record): bool => $record->status === 'draft')
                    ->action(function (Prescription $record, SigcmDatabaseService $service): void {
                        try {
                            $service->emitirReceta($record, Auth::id());
                            Notification::make()->title('Receta emitida')->success()->send();
                        } catch (Throwable $exception) {
                            Notification::make()
                                ->title('No se pudo emitir la receta')
                                ->body($service->formatDatabaseException($exception))
                                ->danger()
                                ->send();
                        }
                    }),
                Action::make('sustituir')
                    ->label(__('sigcm.clinical.replace_prescription'))
                    ->icon(Heroicon::OutlinedArrowPath)
                    ->color('warning')
                    ->visible(fn (Prescription $record): bool => $record->status === 'issued')
                    ->schema([
                        Textarea::make('notes')
                            ->label(__('sigcm.fields.notes'))
                            ->required()
                            ->rows(2),
                    ])
                    ->action(function (Prescription $record, array $data, SigcmDatabaseService $service): void {
                        try {
                            $newId = $service->sustituirRecetaSegura(
                                oldPrescriptionId: (int) $record->id,
                                actorUserId: Auth::id(),
                                notes: (string) $data['notes'],
                            );

                            Notification::make()
                                ->title("Receta sustituida por REC-#{$newId} (borrador)")
                                ->success()
                                ->send();
                        } catch (Throwable $exception) {
                            Notification::make()
                                ->title('No se pudo sustituir la receta')
                                ->body($service->formatDatabaseException($exception))
                                ->danger()
                                ->send();
                        }
                    }),
                Action::make('imprimir')
                    ->label(__('sigcm.clinical.print_prescription'))
                    ->icon(Heroicon::OutlinedPrinter)
                    ->url(fn (Prescription $record): string => route('sigcm.print.prescription', $record))
                    ->openUrlInNewTab(),
            ]);
    }
}
