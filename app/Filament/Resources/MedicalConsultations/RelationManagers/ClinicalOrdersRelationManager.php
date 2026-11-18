<?php

namespace App\Filament\Resources\MedicalConsultations\RelationManagers;

use App\Models\ClinicalOrder;
use App\Models\DiagnosticReport;
use App\Models\DiagnosticResultItem;
use App\Services\SigcmDatabaseService;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Throwable;

class ClinicalOrdersRelationManager extends RelationManager
{
    protected static string $relationship = 'clinicalOrders';

    protected static bool $isLazy = false;

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('sigcm.clinical.orders_tab');
    }

    public function isReadOnly(): bool
    {
        return ! (Auth::user()?->can('create', $this->getOwnerRecord()::class) ?? false);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('order_type')
                    ->label(__('sigcm.fields.order_type'))
                    ->options([
                        'lab' => 'Laboratorio',
                        'imaging' => 'Imagenología',
                        'procedure' => 'Procedimiento',
                        'other' => 'Otro',
                    ])
                    ->default('lab')
                    ->required(),
                Select::make('priority')
                    ->label(__('sigcm.fields.priority'))
                    ->options([
                        'routine' => 'Rutina',
                        'urgent' => 'Urgente',
                    ])
                    ->default('routine')
                    ->required(),
                TextInput::make('title')
                    ->label(__('sigcm.fields.order_title'))
                    ->required()
                    ->maxLength(180)
                    ->columnSpanFull(),
            ]);
    }

    public function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('id')->label(__('sigcm.fields.order'))->formatStateUsing(fn ($state): string => "ORD-#{$state}"),
                TextEntry::make('order_type')->label(__('sigcm.fields.order_type'))->badge(),
                TextEntry::make('title')->label(__('sigcm.fields.order_title'))->columnSpanFull(),
                TextEntry::make('priority')->label(__('sigcm.fields.priority'))->badge(),
                TextEntry::make('status')->label(__('sigcm.fields.status'))->badge(),
                RepeatableEntry::make('diagnosticReports')
                    ->label(__('sigcm.fields.report_status'))
                    ->schema([
                        TextEntry::make('status')
                            ->label(__('sigcm.fields.status'))
                            ->badge()
                            ->formatStateUsing(fn (string $state): string => __("enums.report_status.{$state}")),
                        TextEntry::make('interpretation')->label(__('sigcm.fields.interpretation'))->placeholder('-'),
                        TextEntry::make('reviewer.name')->label(__('sigcm.fields.reviewer'))->placeholder('Pendiente'),
                        RepeatableEntry::make('resultItems')
                            ->label(__('sigcm.fields.parameter'))
                            ->columns(3)
                            ->schema([
                                TextEntry::make('test_name')->label(__('sigcm.fields.parameter')),
                                TextEntry::make('value_display')
                                    ->label(__('sigcm.fields.result'))
                                    ->state(fn ($record): string => $record->result_text ?? (string) $record->result_number),
                                TextEntry::make('reference_range')->label(__('sigcm.fields.reference_range'))->placeholder('-'),
                            ])
                            ->columnSpanFull(),
                    ])
                    ->placeholder('Sin informes recibidos aún.')
                    ->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('title')
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['diagnosticReports.resultItems', 'diagnosticReports.reviewer']))
            ->columns([
                TextColumn::make('id')
                    ->label(__('sigcm.fields.order'))
                    ->formatStateUsing(fn ($state): string => "ORD-#{$state}")
                    ->sortable(),
                TextColumn::make('order_type')
                    ->label(__('sigcm.fields.order_type'))
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'lab' => 'Laboratorio',
                        'imaging' => 'Imagen',
                        'procedure' => 'Procedimiento',
                        default => 'Otro',
                    }),
                TextColumn::make('title')
                    ->label(__('sigcm.fields.order_title'))
                    ->searchable(),
                TextColumn::make('priority')
                    ->label(__('sigcm.fields.priority'))
                    ->badge()
                    ->color(fn (string $state): string => $state === 'urgent' ? 'danger' : 'gray'),
                TextColumn::make('status')
                    ->label(__('sigcm.fields.status'))
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => __("enums.order_status.{$state}"))
                    ->color(fn (string $state): string => match ($state) {
                        'completed' => 'success',
                        'cancelled' => 'danger',
                        default => 'warning',
                    }),
                TextColumn::make('latest_report_status')
                    ->label(__('sigcm.fields.report_status'))
                    ->badge()
                    ->state(fn (ClinicalOrder $record): string => ($report = $record->diagnosticReports->sortByDesc('id')->first())
                        ? __("enums.report_status.{$report->status}")
                        : 'Sin informe')
                    ->color(fn (ClinicalOrder $record): string => match ($record->diagnosticReports->sortByDesc('id')->first()?->status) {
                        'reviewed', 'amended' => 'success',
                        'received' => 'info',
                        default => 'gray',
                    }),
            ])
            ->defaultSort('id', 'desc')
            ->headerActions([
                CreateAction::make()
                    ->label(__('sigcm.actions.new_order'))
                    ->mutateDataUsing(fn (array $data): array => [
                        ...$data,
                        'status' => 'ordered',
                        'ordered_at' => now(),
                        'created_at' => now(),
                    ]),
            ])
            ->recordActions([
                ViewAction::make(),
                Action::make('registrarInforme')
                    ->label(__('sigcm.clinical.register_report'))
                    ->icon(Heroicon::OutlinedDocumentPlus)
                    ->color('info')
                    ->visible(fn (ClinicalOrder $record): bool => $record->status !== 'cancelled')
                    ->schema([
                        Textarea::make('interpretation')
                            ->label(__('sigcm.fields.interpretation'))
                            ->required()
                            ->rows(2),
                        Repeater::make('items')
                            ->label(__('sigcm.fields.result'))
                            ->defaultItems(1)
                            ->minItems(1)
                            ->columns(2)
                            ->schema([
                                TextInput::make('test_code')->label(__('sigcm.fields.code'))->maxLength(70),
                                TextInput::make('test_name')->label(__('sigcm.fields.parameter'))->required()->maxLength(180),
                                Select::make('value_kind')
                                    ->label(__('sigcm.fields.value_kind'))
                                    ->options([
                                        'number' => 'Numérico',
                                        'text' => 'Cualitativo / Texto',
                                    ])
                                    ->default('number')
                                    ->live()
                                    ->required(),
                                TextInput::make('unit_code')->label(__('sigcm.fields.unit'))->maxLength(32),
                                TextInput::make('result_number')
                                    ->label(__('sigcm.fields.result_number'))
                                    ->numeric()
                                    ->step('0.001')
                                    ->visible(fn (Get $get): bool => $get('value_kind') === 'number')
                                    ->required(fn (Get $get): bool => $get('value_kind') === 'number'),
                                TextInput::make('result_text')
                                    ->label(__('sigcm.fields.result_text'))
                                    ->visible(fn (Get $get): bool => $get('value_kind') === 'text')
                                    ->required(fn (Get $get): bool => $get('value_kind') === 'text')
                                    ->maxLength(255),
                                TextInput::make('reference_range')->label(__('sigcm.fields.reference_range'))->maxLength(100)->columnSpanFull(),
                            ]),
                    ])
                    ->action(function (ClinicalOrder $record, array $data): void {
                        try {
                            DB::transaction(static function () use ($record, $data): void {
                                $report = DiagnosticReport::query()->create([
                                    'clinical_order_id' => $record->id,
                                    'reviewed_by_user_id' => null,
                                    'status' => 'received',
                                    'interpretation' => (string) $data['interpretation'],
                                    'issued_at' => now(),
                                    'reviewed_at' => null,
                                    'created_at' => now(),
                                ]);

                                foreach ($data['items'] as $item) {
                                    $isNumeric = ($item['value_kind'] ?? 'number') === 'number';
                                    DiagnosticResultItem::query()->create([
                                        'diagnostic_report_id' => $report->id,
                                        'test_code' => $item['test_code'] ?? null,
                                        'test_name' => (string) $item['test_name'],
                                        'result_number' => $isNumeric ? $item['result_number'] : null,
                                        'result_text' => $isNumeric ? null : (string) $item['result_text'],
                                        'unit_code' => $item['unit_code'] ?? null,
                                        'reference_range' => $item['reference_range'] ?? null,
                                        'created_at' => now(),
                                    ]);
                                }

                                $record->update(['status' => 'completed']);
                            });

                            Notification::make()->title('Informe diagnóstico registrado')->success()->send();
                        } catch (Throwable $exception) {
                            Notification::make()
                                ->title('No se pudo registrar el informe')
                                ->body(app(SigcmDatabaseService::class)->formatDatabaseException($exception))
                                ->danger()
                                ->send();
                        }
                    }),
                Action::make('marcarRevisado')
                    ->label(__('sigcm.clinical.review_report'))
                    ->icon(Heroicon::OutlinedCheckBadge)
                    ->color('success')
                    ->visible(function (ClinicalOrder $record): bool {
                        $latest = $record->diagnosticReports->sortByDesc('id')->first();

                        return $latest !== null && $latest->status === 'received';
                    })
                    ->schema([
                        Textarea::make('interpretation')
                            ->label(__('sigcm.fields.interpretation'))
                            ->rows(2),
                    ])
                    ->action(function (ClinicalOrder $record, array $data, SigcmDatabaseService $service): void {
                        $latest = $record->diagnosticReports->sortByDesc('id')->firstOrFail();
                        $service->revisarInformeDiagnostico($latest, (int) Auth::id(), $data['interpretation'] ?? null);
                        Notification::make()->title('Informe marcado como revisado')->success()->send();
                    }),
            ]);
    }
}
