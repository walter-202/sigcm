<?php

namespace App\Filament\Resources\ClinicalRecords\RelationManagers;

use App\Enums\EpisodeStatus;
use App\Models\CareEpisode;
use App\Models\CarePlan;
use App\Models\CarePlanActivity;
use App\Models\MedicalConsultation;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
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
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class CareEpisodesRelationManager extends RelationManager
{
    protected static string $relationship = 'careEpisodes';

    protected static bool $isLazy = false;

    protected static ?string $title = 'Episodios de atención y planes de cuidado';

    protected static ?string $modelLabel = 'Episodio';

    protected static ?string $pluralModelLabel = 'Episodios';

    public function isReadOnly(): bool
    {
        return ! (Auth::user()?->can('update', $this->getOwnerRecord()) ?? false);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('title')
                    ->label(__('sigcm.fields.episode_title'))
                    ->required()
                    ->maxLength(180)
                    ->columnSpanFull(),
                Select::make('status')
                    ->label(__('sigcm.fields.status'))
                    ->options(EpisodeStatus::class)
                    ->required(),
                DateTimePicker::make('closed_at')
                    ->label(__('sigcm.fields.closed_at'))
                    ->seconds(false)
                    ->nullable(),
            ]);
    }

    public function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('id')
                    ->label(__('sigcm.fields.id'))
                    ->numeric(),
                TextEntry::make('opening_consultation_id')
                    ->label(__('sigcm.fields.opening_consultation'))
                    ->formatStateUsing(fn ($state): string => "Consulta #{$state}"),
                TextEntry::make('title')
                    ->label(__('sigcm.fields.episode_title')),
                TextEntry::make('status')
                    ->label(__('sigcm.fields.status'))
                    ->badge(),
                TextEntry::make('opened_at')
                    ->label(__('sigcm.fields.opened_at'))
                    ->dateTime('d/m/Y H:i'),
                TextEntry::make('closed_at')
                    ->label(__('sigcm.fields.closed_at'))
                    ->dateTime('d/m/Y H:i')
                    ->placeholder('En curso'),
                RepeatableEntry::make('conditions')
                    ->label(__('sigcm.patients.conditions_tab'))
                    ->columns(2)
                    ->schema([
                        TextEntry::make('code')->label(__('sigcm.fields.code'))->badge(),
                        TextEntry::make('display')->label(__('sigcm.fields.display')),
                    ])
                    ->placeholder('Sin condiciones vinculadas.')
                    ->columnSpanFull(),
                RepeatableEntry::make('carePlans')
                    ->label(__('sigcm.clinical.manage_care_plan'))
                    ->schema([
                        TextEntry::make('objective')->label(__('sigcm.fields.objective'))->columnSpanFull(),
                        TextEntry::make('status')->label(__('sigcm.fields.status'))->badge(),
                        RepeatableEntry::make('activities')
                            ->label(__('sigcm.fields.activities'))
                            ->columns(3)
                            ->schema([
                                TextEntry::make('description')->label(__('sigcm.fields.activity_description')),
                                TextEntry::make('status')->label(__('sigcm.fields.status'))->badge(),
                                TextEntry::make('due_at')->label(__('sigcm.fields.date'))->dateTime('d/m/Y H:i')->placeholder('-'),
                            ])
                            ->columnSpanFull(),
                    ])
                    ->placeholder('Sin planes de cuidado registrados.')
                    ->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('title')
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with([
                'conditions',
                'carePlans.activities',
            ])->withCount(['carePlans', 'followUpPlans']))
            ->columns([
                TextColumn::make('id')
                    ->label(__('sigcm.fields.id'))
                    ->numeric()
                    ->sortable(),
                TextColumn::make('title')
                    ->label(__('sigcm.fields.episode'))
                    ->searchable(),
                TextColumn::make('opening_consultation_id')
                    ->label(__('sigcm.fields.opening_consultation'))
                    ->formatStateUsing(fn ($state): string => "#{$state}")
                    ->sortable(),
                TextColumn::make('status')
                    ->label(__('sigcm.fields.status'))
                    ->badge()
                    ->formatStateUsing(fn ($state): string => $state instanceof EpisodeStatus ? $state->getLabel() : (string) $state),
                TextColumn::make('care_plans_count')
                    ->label(__('sigcm.fields.care_plans_count'))
                    ->badge()
                    ->color('info'),
                TextColumn::make('follow_up_plans_count')
                    ->label(__('sigcm.fields.follow_ups_count'))
                    ->badge()
                    ->color('primary'),
                TextColumn::make('opened_at')
                    ->label(__('sigcm.fields.opened_at'))
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
                TextColumn::make('closed_at')
                    ->label(__('sigcm.fields.closed_at'))
                    ->dateTime('d/m/Y H:i')
                    ->placeholder('Activo')
                    ->sortable(),
            ])
            ->defaultSort('id', 'desc')
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                Action::make('crearPlanCuidados')
                    ->label(__('sigcm.clinical.manage_care_plan'))
                    ->icon(Heroicon::OutlinedClipboardDocumentList)
                    ->color('primary')
                    ->schema([
                        Select::make('created_from_consultation_id')
                            ->label(__('sigcm.fields.opened_from_consultation'))
                            ->options(fn (CareEpisode $record): array => MedicalConsultation::query()
                                ->where('patient_id', $this->getOwnerRecord()->patient_id)
                                ->orderByDesc('occurred_at')
                                ->get(['id', 'chief_complaint'])
                                ->mapWithKeys(fn (MedicalConsultation $consultation): array => [
                                    $consultation->id => "#{$consultation->id} · {$consultation->chief_complaint}",
                                ])
                                ->all())
                            ->default(fn (CareEpisode $record): int => (int) $record->opening_consultation_id)
                            ->required(),
                        Textarea::make('objective')
                            ->label(__('sigcm.fields.objective'))
                            ->required()
                            ->rows(2),
                        Repeater::make('activities')
                            ->label(__('sigcm.fields.activities'))
                            ->defaultItems(1)
                            ->minItems(1)
                            ->columns(2)
                            ->schema([
                                TextInput::make('description')
                                    ->label(__('sigcm.fields.activity_description'))
                                    ->required()
                                    ->columnSpanFull(),
                                Select::make('status')
                                    ->label(__('sigcm.fields.status'))
                                    ->options([
                                        'pending' => 'Pendiente',
                                        'done' => 'Realizada',
                                        'cancelled' => 'Cancelada',
                                    ])
                                    ->default('pending')
                                    ->required(),
                                DateTimePicker::make('due_at')
                                    ->label(__('sigcm.fields.due_at'))
                                    ->seconds(false),
                            ]),
                    ])
                    ->action(function (CareEpisode $record, array $data): void {
                        DB::transaction(static function () use ($record, $data): void {
                            $plan = CarePlan::query()->create([
                                'episode_id' => $record->id,
                                'created_from_consultation_id' => (int) $data['created_from_consultation_id'],
                                'status' => 'active',
                                'objective' => (string) $data['objective'],
                                'created_at' => now(),
                            ]);

                            foreach ($data['activities'] as $activity) {
                                CarePlanActivity::query()->create([
                                    'care_plan_id' => $plan->id,
                                    'description' => (string) $activity['description'],
                                    'status' => (string) ($activity['status'] ?? 'pending'),
                                    'due_at' => $activity['due_at'] ?? null,
                                    'created_at' => now(),
                                ]);
                            }
                        });

                        Notification::make()->title('Plan de cuidados registrado')->success()->send();
                    }),
            ]);
    }
}
