<?php

namespace App\Filament\Resources\ClinicalRecords\RelationManagers;

use App\Enums\AppointmentStatus;
use App\Enums\ConsultationStatus;
use App\Filament\Resources\Appointments\AppointmentResource;
use App\Filament\Resources\MedicalConsultations\MedicalConsultationResource;
use App\Models\Appointment;
use App\Models\CareEpisode;
use App\Models\ClinicalRecord;
use App\Models\Doctor;
use App\Models\FollowUpPlan;
use App\Models\MedicalConsultation;
use App\Models\Patient;
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

class ClinicalFollowUpPlansRelationManager extends RelationManager
{
    protected static string $relationship = 'followUpPlans';

    protected static bool $isLazy = false;

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('sigcm.clinical.all_follow_ups_tab');
    }

    public function isReadOnly(): bool
    {
        $user = Auth::user();

        return ! (($user?->can('create', MedicalConsultation::class) ?? false)
            || ($user?->can('create', Appointment::class) ?? false));
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('originating_consultation_id')
                    ->label(__('sigcm.fields.originating_consultation'))
                    ->options(fn (): array => MedicalConsultation::query()
                        ->with('doctor')
                        ->where('patient_id', $this->resolvePatientId())
                        ->where('status', ConsultationStatus::Signed->value)
                        ->orderByDesc('occurred_at')
                        ->get()
                        ->mapWithKeys(fn (MedicalConsultation $consultation): array => [
                            $consultation->id => "CON-#{$consultation->id} · {$consultation->occurred_at?->format('d/m/Y')} · {$consultation->doctor?->full_name}",
                        ])
                        ->all())
                    ->searchable()
                    ->required(),
                Select::make('episode_id')
                    ->label(__('sigcm.fields.care_episode'))
                    ->options(fn (): array => CareEpisode::query()
                        ->whereHas('clinicalRecord', fn (Builder $query): Builder => $query->where('patient_id', $this->resolvePatientId()))
                        ->orderByDesc('opened_at')
                        ->pluck('title', 'id')
                        ->all())
                    ->nullable(),
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
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with([
                'episode',
                'originatingConsultation.doctor',
                'appointment.medicalConsultation',
                'resultingConsultation',
            ]))
            ->columns([
                TextColumn::make('id')
                    ->label(__('sigcm.fields.plan'))
                    ->formatStateUsing(fn ($state): string => "SEG-#{$state}")
                    ->sortable(),
                TextColumn::make('episode.title')
                    ->label(__('sigcm.fields.episode'))
                    ->placeholder('Sin episodio vinculado')
                    ->limit(30),
                TextColumn::make('originating_consultation_id')
                    ->label(__('sigcm.fields.originating_consultation'))
                    ->badge()
                    ->color('info')
                    ->formatStateUsing(fn ($state): string => "CON-#{$state}")
                    ->url(fn (FollowUpPlan $record): ?string => $record->originating_consultation_id
                        ? MedicalConsultationResource::getUrl('view', ['record' => $record->originating_consultation_id])
                        : null),
                TextColumn::make('goal')
                    ->label(__('sigcm.fields.goal'))
                    ->limit(45)
                    ->tooltip(fn (FollowUpPlan $record): string => (string) $record->goal),
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
                    ->badge()
                    ->color(fn ($state): string => $state ? 'info' : 'gray')
                    ->formatStateUsing(fn ($state): string => $state ? "CITA-#{$state}" : 'Sin cita')
                    ->url(fn (FollowUpPlan $record): ?string => $record->appointment_id
                        ? AppointmentResource::getUrl('view', ['record' => $record->appointment_id])
                        : null)
                    ->placeholder('Sin cita'),
                TextColumn::make('resulting_consultation_id')
                    ->label(__('sigcm.fields.resulting_consultation'))
                    ->badge()
                    ->color(fn ($state): string => $state ? 'success' : 'gray')
                    ->formatStateUsing(fn ($state): string => $state ? "CON-#{$state}" : 'Pendiente')
                    ->url(fn (FollowUpPlan $record): ?string => $record->resulting_consultation_id
                        ? MedicalConsultationResource::getUrl('view', ['record' => $record->resulting_consultation_id])
                        : null)
                    ->placeholder('Pendiente'),
            ])
            ->defaultSort('id', 'desc')
            ->headerActions([
                CreateAction::make()
                    ->label(__('sigcm.actions.new_follow_up'))
                    ->visible(fn (): bool => Auth::user()?->can('create', MedicalConsultation::class) ?? false)
                    ->using(fn (array $data): FollowUpPlan => FollowUpPlan::query()->create([
                        ...$data,
                        'status' => 'pending',
                    ])),
            ])
            ->recordActions([
                Action::make('programarCitaControl')
                    ->label(__('sigcm.clinical.schedule_follow_up'))
                    ->icon(Heroicon::OutlinedCalendarDays)
                    ->color('primary')
                    ->visible(fn (FollowUpPlan $record): bool => $record->status === 'pending'
                        && $record->appointment_id === null
                        && (Auth::user()?->can('create', Appointment::class) ?? false))
                    ->schema([
                        Select::make('doctor_id')
                            ->label(__('sigcm.fields.doctor'))
                            ->options(fn (): array => Doctor::query()
                                ->where('is_active', true)
                                ->get()
                                ->mapWithKeys(fn (Doctor $doctor): array => [$doctor->id => $doctor->full_name])
                                ->all())
                            ->default(fn (FollowUpPlan $record): ?int => $record->originatingConsultation?->doctor_id)
                            ->required(),
                        DateTimePicker::make('starts_at')
                            ->label(__('sigcm.fields.starts_at'))
                            ->seconds(false)
                            ->default(fn (FollowUpPlan $record) => $record->due_at)
                            ->required(),
                        DateTimePicker::make('ends_at')
                            ->label(__('sigcm.fields.ends_at'))
                            ->seconds(false)
                            ->default(fn (FollowUpPlan $record) => $record->due_at?->copy()->addMinutes(30))
                            ->after('starts_at')
                            ->required(),
                    ])
                    ->action(function (FollowUpPlan $record, array $data): void {
                        $patientId = $this->resolvePatientId();

                        try {
                            DB::transaction(static function () use ($record, $data, $patientId): void {
                                $appointment = Appointment::query()->create([
                                    'patient_id' => $patientId,
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
                Action::make('atenderControl')
                    ->label(__('sigcm.actions.attend'))
                    ->icon(Heroicon::OutlinedClipboardDocumentCheck)
                    ->color('success')
                    ->visible(fn (FollowUpPlan $record): bool => $record->appointment_id !== null
                        && $record->resulting_consultation_id === null
                        && $record->appointment?->medicalConsultation === null
                        && (Auth::user()?->can('create', MedicalConsultation::class) ?? false))
                    ->url(fn (FollowUpPlan $record): string => MedicalConsultationResource::getUrl('create', [
                        'appointment_id' => $record->appointment_id,
                    ])),
            ]);
    }

    private function resolvePatientId(): int
    {
        $owner = $this->getOwnerRecord();

        if ($owner instanceof ClinicalRecord) {
            return (int) $owner->patient_id;
        }

        if ($owner instanceof Patient) {
            return (int) $owner->id;
        }

        return 0;
    }
}
