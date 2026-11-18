<?php

namespace App\Filament\Widgets;

use App\Enums\AppointmentStatus;
use App\Filament\Resources\Appointments\AppointmentResource;
use App\Filament\Resources\Appointments\Schemas\AppointmentForm;
use App\Filament\Resources\MedicalConsultations\MedicalConsultationResource;
use App\Models\Appointment;
use App\Models\MedicalConsultation;
use App\Services\SigcmDatabaseService;
use Filament\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Guava\Calendar\Enums\CalendarViewType;
use Guava\Calendar\Filament\CalendarWidget;
use Guava\Calendar\ValueObjects\CalendarEvent;
use Guava\Calendar\ValueObjects\DateClickInfo;
use Guava\Calendar\ValueObjects\EventDropInfo;
use Guava\Calendar\ValueObjects\FetchInfo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\On;
use Throwable;

class AgendaCalendarWidget extends CalendarWidget
{
    protected static bool $isDiscovered = false;

    protected static bool $isLazy = false;

    protected CalendarViewType $calendarView = CalendarViewType::TimeGridWeek;

    protected ?string $locale = 'es';

    protected bool $eventClickEnabled = true;

    protected bool $dateClickEnabled = true;

    protected bool $eventDragEnabled = true;

    protected bool $dayMaxEvents = true;

    public ?int $doctorId = null;

    public ?string $statusFilter = null;

    public function getHeading(): string
    {
        return __('sigcm.schedule.title');
    }

    /**
     * @return array<string, mixed>
     */
    public function getOptions(): array
    {
        return [
            'allDaySlot' => false,
            'slotMinTime' => '07:00:00',
            'slotMaxTime' => '20:00:00',
            'nowIndicator' => true,
            'headerToolbar' => [
                'start' => 'title',
                'center' => '',
                'end' => 'dayGridMonth,timeGridWeek,timeGridDay,listWeek today prev,next',
            ],
            'buttonText' => [
                'today' => 'Hoy',
                'dayGridMonth' => 'Mes',
                'timeGridWeek' => 'Semana',
                'timeGridDay' => 'Día',
                'listWeek' => 'Lista',
            ],
        ];
    }

    #[On('agenda-filter-updated')]
    public function applyFilters(?int $doctorId = null, ?string $statusFilter = null): void
    {
        $this->doctorId = $doctorId ?: null;
        $this->statusFilter = $statusFilter ?: null;
        $this->refreshRecords();
    }

    /**
     * @return Collection<int, CalendarEvent>
     */
    protected function getEvents(FetchInfo $info): Collection
    {
        return Appointment::query()
            ->with(['patient', 'doctor', 'medicalConsultation'])
            ->when($this->doctorId !== null, fn (Builder $query): Builder => $query->where('doctor_id', $this->doctorId))
            ->when(filled($this->statusFilter), fn (Builder $query): Builder => $query->where('status', $this->statusFilter))
            ->where('starts_at', '<', $info->end)
            ->where('ends_at', '>', $info->start)
            ->orderBy('starts_at')
            ->get()
            ->map(function (Appointment $appointment): CalendarEvent {
                [$bgColor, $textColor] = $this->resolveStatusColors($appointment->status);
                $isActive = in_array($appointment->status, [
                    AppointmentStatus::Pending,
                    AppointmentStatus::Confirmed,
                    AppointmentStatus::CheckedIn,
                ], true);

                $patientName = $appointment->patient?->full_name ?? "Paciente #{$appointment->patient_id}";
                $doctorLast = $appointment->doctor?->last_names ?? 'Médico';

                return CalendarEvent::make($appointment)
                    ->title("{$patientName} · {$doctorLast}")
                    ->start($appointment->starts_at)
                    ->end($appointment->ends_at)
                    ->backgroundColor($bgColor)
                    ->textColor($textColor)
                    ->editable($isActive);
            });
    }

    protected function onDateClick(DateClickInfo $info): void
    {
        if (! (Auth::user()?->can('create', Appointment::class) ?? false)) {
            return;
        }

        $start = $info->date->copy();
        if ($info->allDay) {
            $start = $start->setTime(9, 0);
        }

        $this->mountAction('createAppointment', [
            'starts_at' => $start->format('Y-m-d H:i:s'),
            'ends_at' => $start->addMinutes(30)->format('Y-m-d H:i:s'),
            'doctor_id' => $this->doctorId,
        ]);
    }

    protected function onEventDrop(EventDropInfo $info, Model $event): bool
    {
        if (! $event instanceof Appointment) {
            return false;
        }

        if (! (Auth::user()?->can('update', $event) ?? false)) {
            Notification::make()
                ->title('Permiso denegado')
                ->body('No tienes autorización para reprogramar citas.')
                ->danger()
                ->send();

            return false;
        }

        $service = app(SigcmDatabaseService::class);

        try {
            $service->reprogramarCitaSegura(
                appointmentId: (int) $event->id,
                changedByUserId: Auth::id(),
                newStart: $info->event->getStart()->format('Y-m-d H:i:sP'),
                newEnd: $info->event->getEnd()->format('Y-m-d H:i:sP'),
                reason: __('sigcm.schedule.reschedule_drag_reason'),
            );

            Notification::make()
                ->title(__('sigcm.schedule.rescheduled_ok'))
                ->success()
                ->send();

            $this->refreshRecords();

            return true;
        } catch (Throwable $exception) {
            Notification::make()
                ->title(__('sigcm.schedule.reschedule_error'))
                ->body($service->formatDatabaseException($exception))
                ->danger()
                ->persistent()
                ->send();

            return false;
        }
    }

    public function createAppointmentAction(): Action
    {
        return Action::make('createAppointment')
            ->label(__('sigcm.actions.new_appointment'))
            ->modalHeading(__('sigcm.actions.new_appointment'))
            ->model(Appointment::class)
            ->schema(fn (Schema $schema): Schema => AppointmentForm::configure($schema)->model(Appointment::class))
            ->fillForm(fn (array $arguments): array => [
                'doctor_id' => $arguments['doctor_id'] ?? $this->doctorId,
                'starts_at' => $arguments['starts_at'] ?? now()->addHour()->startOfHour()->format('Y-m-d H:i:s'),
                'ends_at' => $arguments['ends_at'] ?? now()->addHour()->startOfHour()->addMinutes(30)->format('Y-m-d H:i:s'),
                'status' => AppointmentStatus::Pending->value,
            ])
            ->action(function (array $data): void {
                $service = app(SigcmDatabaseService::class);

                try {
                    DB::transaction(static function () use ($data): void {
                        Appointment::query()->create([
                            ...$data,
                            'created_by_user_id' => Auth::id(),
                        ]);
                    });

                    Notification::make()
                        ->title(__('sigcm.schedule.created_ok'))
                        ->success()
                        ->send();

                    $this->refreshRecords();
                } catch (Throwable $exception) {
                    Notification::make()
                        ->title(__('sigcm.schedule.create_error'))
                        ->body($service->formatDatabaseException($exception))
                        ->danger()
                        ->persistent()
                        ->send();
                }
            });
    }

    /**
     * @return array<Action>
     */
    public function getEventClickContextMenuActions(): array
    {
        return [
            Action::make('atenderCita')
                ->label(__('sigcm.actions.attend'))
                ->icon(Heroicon::OutlinedClipboardDocumentCheck)
                ->color('success')
                ->record(fn (): ?Model => $this->getEventRecord())
                ->visible(fn (?Appointment $record): bool => $record !== null
                    && $record->medicalConsultation === null
                    && in_array($record->status, [
                        AppointmentStatus::Pending,
                        AppointmentStatus::Confirmed,
                        AppointmentStatus::CheckedIn,
                    ], true)
                    && (Auth::user()?->can('create', MedicalConsultation::class) ?? false))
                ->url(fn (Appointment $record): string => MedicalConsultationResource::getUrl('create', [
                    'appointment_id' => $record->id,
                ])),

            Action::make('confirmarCita')
                ->label(__('sigcm.actions.confirm'))
                ->icon(Heroicon::OutlinedCheckCircle)
                ->color('info')
                ->record(fn (): ?Model => $this->getEventRecord())
                ->visible(fn (?Appointment $record): bool => $record?->status === AppointmentStatus::Pending)
                ->action(function (Appointment $record): void {
                    $this->runAppointmentTransition(fn () => app(SigcmDatabaseService::class)->confirmarCita($record, Auth::id()), 'Cita confirmada');
                }),

            Action::make('registrarLlegada')
                ->label(__('sigcm.actions.check_in'))
                ->icon(Heroicon::OutlinedCheckBadge)
                ->color('primary')
                ->record(fn (): ?Model => $this->getEventRecord())
                ->visible(fn (?Appointment $record): bool => $record !== null && in_array($record->status, [
                    AppointmentStatus::Pending,
                    AppointmentStatus::Confirmed,
                ], true))
                ->action(function (Appointment $record): void {
                    $this->runAppointmentTransition(fn () => app(SigcmDatabaseService::class)->registrarLlegadaCita($record, Auth::id()), 'Paciente registrado en sala');
                }),

            Action::make('reprogramarCita')
                ->label(__('sigcm.actions.reschedule'))
                ->icon(Heroicon::OutlinedCalendarDays)
                ->color('warning')
                ->record(fn (): ?Model => $this->getEventRecord())
                ->visible(fn (?Appointment $record): bool => $record !== null && in_array($record->status, [
                    AppointmentStatus::Pending,
                    AppointmentStatus::Confirmed,
                    AppointmentStatus::CheckedIn,
                ], true))
                ->schema([
                    DateTimePicker::make('new_start')
                        ->label(__('sigcm.fields.new_starts_at'))
                        ->seconds(false)
                        ->required(),
                    DateTimePicker::make('new_end')
                        ->label(__('sigcm.fields.new_ends_at'))
                        ->seconds(false)
                        ->after('new_start')
                        ->required(),
                    TextInput::make('reason')
                        ->label(__('sigcm.fields.reschedule_reason'))
                        ->required()
                        ->maxLength(255),
                ])
                ->fillForm(fn (Appointment $record): array => [
                    'new_start' => $record->starts_at,
                    'new_end' => $record->ends_at,
                ])
                ->action(function (Appointment $record, array $data): void {
                    $service = app(SigcmDatabaseService::class);
                    try {
                        $service->reprogramarCitaSegura(
                            appointmentId: (int) $record->id,
                            changedByUserId: Auth::id(),
                            newStart: (string) $data['new_start'],
                            newEnd: (string) $data['new_end'],
                            reason: (string) $data['reason'],
                        );
                        Notification::make()->title(__('sigcm.schedule.rescheduled_ok'))->success()->send();
                        $this->refreshRecords();
                    } catch (Throwable $exception) {
                        Notification::make()
                            ->title(__('sigcm.schedule.reschedule_error'))
                            ->body($service->formatDatabaseException($exception))
                            ->danger()
                            ->persistent()
                            ->send();
                    }
                }),

            Action::make('marcarNoAsistio')
                ->label(__('sigcm.actions.no_show'))
                ->icon(Heroicon::OutlinedUserMinus)
                ->color('gray')
                ->record(fn (): ?Model => $this->getEventRecord())
                ->requiresConfirmation()
                ->visible(fn (?Appointment $record): bool => $record !== null && in_array($record->status, [
                    AppointmentStatus::Pending,
                    AppointmentStatus::Confirmed,
                ], true))
                ->action(function (Appointment $record): void {
                    $this->runAppointmentTransition(fn () => app(SigcmDatabaseService::class)->marcarNoAsistioCita($record, Auth::id()), 'Cita marcada como inasistencia');
                }),

            Action::make('cancelarCita')
                ->label(__('sigcm.actions.cancel'))
                ->icon(Heroicon::OutlinedXCircle)
                ->color('danger')
                ->record(fn (): ?Model => $this->getEventRecord())
                ->requiresConfirmation()
                ->visible(fn (?Appointment $record): bool => $record !== null && in_array($record->status, [
                    AppointmentStatus::Pending,
                    AppointmentStatus::Confirmed,
                    AppointmentStatus::CheckedIn,
                ], true))
                ->action(function (Appointment $record): void {
                    $this->runAppointmentTransition(fn () => app(SigcmDatabaseService::class)->cancelarCita($record, Auth::id()), 'Cita cancelada');
                }),

            Action::make('verDetalleCita')
                ->label(__('sigcm.actions.view_detail'))
                ->icon(Heroicon::OutlinedEye)
                ->record(fn (): ?Model => $this->getEventRecord())
                ->url(fn (Appointment $record): string => AppointmentResource::getUrl('view', ['record' => $record])),
        ];
    }

    private function runAppointmentTransition(callable $callback, string $successTitle): void
    {
        $service = app(SigcmDatabaseService::class);

        try {
            $callback();
            Notification::make()->title($successTitle)->success()->send();
            $this->refreshRecords();
        } catch (Throwable $exception) {
            Notification::make()
                ->title('Operación rechazada')
                ->body($service->formatDatabaseException($exception))
                ->danger()
                ->send();
        }
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function resolveStatusColors(?AppointmentStatus $status): array
    {
        return match ($status) {
            AppointmentStatus::Pending => ['#FEF3C7', '#92400E'],
            AppointmentStatus::Confirmed => ['#DBEAFE', '#1E40AF'],
            AppointmentStatus::CheckedIn => ['#CCFBF1', '#115E59'],
            AppointmentStatus::Completed => ['#DCFCE7', '#166534'],
            AppointmentStatus::Cancelled => ['#FEE2E2', '#991B1B'],
            AppointmentStatus::NoShow => ['#E2E8F0', '#334155'],
            default => ['#E2E8F0', '#1E293B'],
        };
    }
}
