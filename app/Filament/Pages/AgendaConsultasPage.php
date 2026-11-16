<?php

namespace App\Filament\Pages;

use App\Services\SigcmDatabaseService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use Throwable;

class AgendaConsultasPage extends Page
{
    protected static ?string $slug = 'agenda-consultas';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static ?string $navigationLabel = '2. Citas y Consultas (SP)';

    protected static ?string $title = 'Historial de Citas, Consultas y Transacciones SQL';

    protected static ?int $navigationSort = 2;

    protected string $view = 'filament.pages.agenda-consultas-page';

    /**
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('registrarFirmarConsulta')
                ->label('Registrar y Firmar Consulta (SP)')
                ->icon(Heroicon::OutlinedClipboardDocumentList)
                ->color('primary')
                ->modalHeading('CALL sp_registrar_y_firmar_consulta_segura')
                ->modalDescription('Ejecuta la transacción atómica con bloqueo FOR UPDATE: crea y firma la consulta, abre el expediente si no existe, genera el episodio clínico y registra el evento SIGN en audit_events.')
                ->schema([
                    Select::make('patient_id')
                        ->label('Paciente')
                        ->options(fn (): array => app(SigcmDatabaseService::class)->getPatientOptions())
                        ->searchable()
                        ->required(),
                    Select::make('doctor_id')
                        ->label('Médico')
                        ->options(fn (): array => app(SigcmDatabaseService::class)->getDoctorOptions())
                        ->searchable()
                        ->required(),
                    Select::make('appointment_id')
                        ->label('Cita asociada (opcional; dejar vacío para atención espontánea)')
                        ->options(fn (): array => app(SigcmDatabaseService::class)->getActiveAppointmentOptions(onlyWithoutConsultation: true))
                        ->searchable()
                        ->nullable(),
                    TextInput::make('chief_complaint')
                        ->label('Motivo de consulta (obligatorio para firma)')
                        ->required()
                        ->maxLength(500),
                    TextInput::make('episode_title')
                        ->label('Título del episodio clínico')
                        ->default('Episodio clínico ambulatorio')
                        ->required()
                        ->maxLength(180),
                    Textarea::make('subjective')
                        ->label('Subjetivo (SOAP)')
                        ->rows(2),
                    Textarea::make('objective')
                        ->label('Objetivo (SOAP)')
                        ->rows(2),
                    Textarea::make('assessment')
                        ->label('Valoración (SOAP)')
                        ->rows(2),
                    Textarea::make('plan')
                        ->label('Plan (SOAP)')
                        ->rows(2),
                ])
                ->action(function (array $data): void {
                    $service = app(SigcmDatabaseService::class);

                    try {
                        $service->registrarYFirmarConsultaSegura($data, Auth::id());

                        Notification::make()
                            ->title('Transacción confirmada en PostgreSQL 17')
                            ->body('Consulta firmada, expediente asegurado, episodio creado y bitácora audit_events actualizada mediante trigger.')
                            ->success()
                            ->send();
                    } catch (Throwable $exception) {
                        Notification::make()
                            ->title('Transacción revertida por PostgreSQL')
                            ->body($service->formatDatabaseException($exception))
                            ->danger()
                            ->persistent()
                            ->send();
                    }
                }),

            Action::make('reprogramarCita')
                ->label('Reprogramar Cita (SP + Solapamiento)')
                ->icon(Heroicon::OutlinedCalendarDays)
                ->color('warning')
                ->modalHeading('CALL sp_reprogramar_cita_segura')
                ->modalDescription('Bloquea la cita con FOR UPDATE, registra el historial en appointment_reschedules y valida el solapamiento con trg_validar_solapamiento_cita.')
                ->schema([
                    Select::make('appointment_id')
                        ->label('Cita activa a reprogramar')
                        ->options(fn (): array => app(SigcmDatabaseService::class)->getActiveAppointmentOptions())
                        ->searchable()
                        ->required(),
                    DateTimePicker::make('new_start')
                        ->label('Nueva fecha y hora de inicio')
                        ->seconds(false)
                        ->required(),
                    DateTimePicker::make('new_end')
                        ->label('Nueva fecha y hora de fin')
                        ->seconds(false)
                        ->required(),
                    TextInput::make('reason')
                        ->label('Motivo de reprogramación')
                        ->default('Reprogramación operativa desde panel')
                        ->required()
                        ->maxLength(300),
                ])
                ->action(function (array $data): void {
                    $service = app(SigcmDatabaseService::class);

                    try {
                        $service->reprogramarCitaSegura(
                            appointmentId: (int) $data['appointment_id'],
                            changedByUserId: Auth::id(),
                            newStart: (string) $data['new_start'],
                            newEnd: (string) $data['new_end'],
                            reason: (string) $data['reason'],
                        );

                        Notification::make()
                            ->title('Cita reprogramada con éxito')
                            ->body('Se registró el historial en appointment_reschedules y la auditoría automática en audit_events.')
                            ->success()
                            ->send();
                    } catch (Throwable $exception) {
                        Notification::make()
                            ->title('Reprogramación rechazada (ROLLBACK)')
                            ->body($service->formatDatabaseException($exception))
                            ->danger()
                            ->persistent()
                            ->send();
                    }
                }),

            Action::make('valorizarCursor')
                ->label('Calcular con Cursor')
                ->icon(Heroicon::OutlinedDocumentText)
                ->color('info')
                ->action(function (): void {
                    $result = app(SigcmDatabaseService::class)->valorizarActividadClinicaCursor();

                    Notification::make()
                        ->title('Resultado de CALL sp_valorizar_actividad_clinica')
                        ->body("Consultas firmadas: {$result['consultas_firmadas']} | Citas activas: {$result['citas_activas']} | Horas en agenda: {$result['horas_agenda']} h")
                        ->info()
                        ->send();
                }),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $service = app(SigcmDatabaseService::class);

        return [
            'registros' => $service->getAgendaConsultasDetalle(),
            'cursorSummary' => $service->valorizarActividadClinicaCursor(),
        ];
    }
}
