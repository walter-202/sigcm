<?php

namespace App\Filament\Resources\Appointments;

use App\Enums\AppointmentStatus;
use App\Filament\Resources\Appointments\Pages\CreateAppointment;
use App\Filament\Resources\Appointments\Pages\EditAppointment;
use App\Filament\Resources\Appointments\Pages\ListAppointments;
use App\Filament\Resources\Appointments\Pages\ViewAppointment;
use App\Filament\Resources\Appointments\RelationManagers\ReschedulesRelationManager;
use App\Filament\Resources\Appointments\Schemas\AppointmentForm;
use App\Filament\Resources\Appointments\Schemas\AppointmentInfolist;
use App\Filament\Resources\Appointments\Tables\AppointmentsTable;
use App\Filament\Resources\MedicalConsultations\MedicalConsultationResource;
use App\Models\Appointment;
use App\Models\MedicalConsultation;
use App\Services\SigcmDatabaseService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Throwable;
use UnitEnum;

class AppointmentResource extends Resource
{
    protected static ?string $model = Appointment::class;

    protected static bool $shouldRegisterNavigation = false;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDateRange;

    protected static ?string $modelLabel = 'Cita Médica';

    protected static ?string $pluralModelLabel = 'Citas Médicas';

    protected static ?int $navigationSort = 4;

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('sigcm.nav.clinical_management');
    }

    public static function getNavigationLabel(): string
    {
        return __('sigcm.nav.appointments');
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with([
            'patient',
            'doctor',
            'medicalConsultation',
            'createdByUser',
        ]);
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return AppointmentForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return AppointmentInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AppointmentsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            ReschedulesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAppointments::route('/'),
            'create' => CreateAppointment::route('/create'),
            'view' => ViewAppointment::route('/{record}'),
            'edit' => EditAppointment::route('/{record}/edit'),
        ];
    }

    /**
     * Inicia o continúa la consulta médica vinculada a una cita vigente.
     */
    public static function makeAttendAction(): Action
    {
        return Action::make('atenderConsulta')
            ->label(__('sigcm.actions.attend'))
            ->icon(Heroicon::OutlinedClipboardDocumentCheck)
            ->color('primary')
            ->visible(fn (Appointment $record): bool => $record->medicalConsultation === null
                && in_array($record->status, [
                    AppointmentStatus::Pending,
                    AppointmentStatus::Confirmed,
                    AppointmentStatus::CheckedIn,
                ], true)
                && (Auth::user()?->can('create', MedicalConsultation::class) ?? false))
            ->action(function (Appointment $record, SigcmDatabaseService $service): mixed {
                if (in_array($record->status, [AppointmentStatus::Pending, AppointmentStatus::Confirmed], true)) {
                    try {
                        $service->registrarLlegadaCita($record, Auth::id());
                    } catch (Throwable $exception) {
                        Notification::make()
                            ->title('No se pudo preparar la atención de la cita')
                            ->body($service->formatDatabaseException($exception))
                            ->danger()
                            ->persistent()
                            ->send();

                        return null;
                    }
                }

                return redirect()->to(MedicalConsultationResource::getUrl('create', [
                    'appointment_id' => $record->id,
                ]));
            });
    }

    /**
     * Enlace directo hacia la consulta médica ya registrada dentro de la cita.
     */
    public static function makeViewConsultationAction(): Action
    {
        return Action::make('verConsulta')
            ->label(__('sigcm.fields.consultation'))
            ->icon(Heroicon::OutlinedDocumentText)
            ->color('success')
            ->visible(fn (Appointment $record): bool => $record->medicalConsultation !== null
                && (Auth::user()?->can('view', $record->medicalConsultation) ?? false))
            ->url(fn (Appointment $record): ?string => $record->medicalConsultation !== null
                ? MedicalConsultationResource::getUrl('view', ['record' => $record->medicalConsultation])
                : null);
    }

    /**
     * Acción compartida para reprogramar citas mediante sp_reprogramar_cita_segura.
     */
    public static function makeRescheduleAction(): Action
    {
        return Action::make('reprogramar')
            ->label(__('sigcm.actions.reschedule'))
            ->icon(Heroicon::OutlinedCalendarDays)
            ->color('warning')
            ->visible(fn (Appointment $record): bool => in_array($record->status, [
                AppointmentStatus::Pending,
                AppointmentStatus::Confirmed,
                AppointmentStatus::CheckedIn,
            ], true))
            ->modalHeading('Reprogramar cita médica')
            ->modalDescription('Actualiza el bloque horario verificando disponibilidad del médico y conservando el historial de cambios.')
            ->fillForm(fn (Appointment $record): array => [
                'new_start' => $record->starts_at,
                'new_end' => $record->ends_at,
            ])
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
                    ->maxLength(300),
            ])
            ->action(function (Appointment $record, array $data, SigcmDatabaseService $service): void {
                try {
                    $service->reprogramarCitaSegura(
                        appointmentId: (int) $record->id,
                        changedByUserId: Auth::id(),
                        newStart: (string) $data['new_start'],
                        newEnd: (string) $data['new_end'],
                        reason: (string) $data['reason'],
                    );

                    Notification::make()
                        ->title(__('sigcm.schedule.rescheduled_ok'))
                        ->success()
                        ->send();
                } catch (Throwable $exception) {
                    Notification::make()
                        ->title(__('sigcm.schedule.reschedule_error'))
                        ->body($service->formatDatabaseException($exception))
                        ->danger()
                        ->persistent()
                        ->send();
                }
            });
    }
}
