<?php

namespace App\Filament\Resources\MedicalConsultations\RelationManagers;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\CareEpisode;
use App\Models\Doctor;
use App\Models\FollowUpPlan;
use App\Services\SigcmDatabaseService;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Throwable;

class OriginatedFollowUpPlansRelationManager extends RelationManager
{
    protected static string $relationship = 'originatedFollowUpPlans';

    protected static bool $isLazy = false;

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('sigcm.clinical.follow_ups_tab');
    }

    public function isReadOnly(): bool
    {
        return ! (Auth::user()?->can('create', $this->getOwnerRecord()::class) ?? false);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('episode_id')
                    ->label(__('sigcm.fields.care_episode'))
                    ->options(function (): array {
                        $consultation = $this->getOwnerRecord();

                        return CareEpisode::query()
                            ->whereHas('clinicalRecord', fn (Builder $query): Builder => $query->where('patient_id', $consultation->patient_id))
                            ->orderByDesc('opened_at')
                            ->pluck('title', 'id')
                            ->all();
                    })
                    ->required(),
                DateTimePicker::make('due_at')
                    ->label(__('sigcm.fields.due_at'))
                    ->seconds(false)
                    ->default(now()->addDays(14))
                    ->required(),
                Textarea::make('goal')
                    ->label(__('sigcm.fields.goal'))
                    ->required()
                    ->rows(2)
                    ->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('goal')
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['episode', 'appointment', 'resultingConsultation']))
            ->columns([
                TextColumn::make('id')
                    ->label(__('sigcm.fields.plan'))
                    ->formatStateUsing(fn ($state): string => "SEG-#{$state}")
                    ->sortable(),
                TextColumn::make('episode.title')
                    ->label(__('sigcm.fields.episode')),
                TextColumn::make('goal')
                    ->label(__('sigcm.fields.goal'))
                    ->limit(40),
                TextColumn::make('due_at')
                    ->label(__('sigcm.fields.due_at'))
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
                TextColumn::make('status')
                    ->label(__('sigcm.fields.status'))
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'scheduled' => 'Cita programada',
                        'completed' => 'Cumplido',
                        'missed' => 'Incumplido',
                        'cancelled' => 'Cancelado',
                        default => 'Pendiente',
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'completed' => 'success',
                        'scheduled' => 'info',
                        'missed', 'cancelled' => 'danger',
                        default => 'warning',
                    }),
                TextColumn::make('appointment_id')
                    ->label(__('sigcm.fields.linked_appointment'))
                    ->formatStateUsing(fn ($state): string => $state ? "CITA-#{$state}" : 'Sin cita')
                    ->placeholder('Sin cita'),
            ])
            ->defaultSort('id', 'desc')
            ->headerActions([
                CreateAction::make()
                    ->label(__('sigcm.actions.new_follow_up'))
                    ->mutateDataUsing(fn (array $data): array => [
                        ...$data,
                        'status' => 'pending',
                    ]),
            ])
            ->recordActions([
                Action::make('programarCitaControl')
                    ->label(__('sigcm.clinical.schedule_follow_up'))
                    ->icon(Heroicon::OutlinedCalendarDays)
                    ->color('primary')
                    ->visible(fn (FollowUpPlan $record): bool => $record->status === 'pending' && $record->appointment_id === null)
                    ->schema([
                        Select::make('doctor_id')
                            ->label(__('sigcm.fields.doctor'))
                            ->options(fn (): array => Doctor::query()
                                ->where('is_active', true)
                                ->get()
                                ->mapWithKeys(fn (Doctor $doctor): array => [$doctor->id => $doctor->full_name])
                                ->all())
                            ->default(fn (): int => (int) $this->getOwnerRecord()->doctor_id)
                            ->required(),
                        DateTimePicker::make('starts_at')
                            ->label(__('sigcm.fields.starts_at'))
                            ->seconds(false)
                            ->default(fn (FollowUpPlan $record) => $record->due_at)
                            ->required(),
                        DateTimePicker::make('ends_at')
                            ->label(__('sigcm.fields.ends_at'))
                            ->seconds(false)
                            ->default(fn (FollowUpPlan $record) => $record->due_at->copy()->addMinutes(30))
                            ->after('starts_at')
                            ->required(),
                    ])
                    ->action(function (FollowUpPlan $record, array $data): void {
                        $consultation = $this->getOwnerRecord();

                        try {
                            DB::transaction(static function () use ($record, $data, $consultation): void {
                                $appointment = Appointment::query()->create([
                                    'patient_id' => $consultation->patient_id,
                                    'doctor_id' => (int) $data['doctor_id'],
                                    'created_by_user_id' => Auth::id(),
                                    'starts_at' => $data['starts_at'],
                                    'ends_at' => $data['ends_at'],
                                    'status' => AppointmentStatus::Confirmed,
                                    'reason' => "Control: {$record->goal}",
                                ]);

                                $record->update([
                                    'appointment_id' => $appointment->id,
                                    'status' => 'scheduled',
                                ]);
                            });

                            Notification::make()->title('Cita de control programada')->success()->send();
                        } catch (Throwable $exception) {
                            Notification::make()
                                ->title('No se pudo programar la cita de control')
                                ->body(app(SigcmDatabaseService::class)->formatDatabaseException($exception))
                                ->danger()
                                ->send();
                        }
                    }),
            ]);
    }
}
