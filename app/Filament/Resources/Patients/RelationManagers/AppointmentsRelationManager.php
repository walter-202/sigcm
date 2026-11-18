<?php

namespace App\Filament\Resources\Patients\RelationManagers;

use App\Enums\AppointmentStatus;
use App\Filament\Resources\Appointments\AppointmentResource;
use App\Filament\Resources\MedicalConsultations\MedicalConsultationResource;
use App\Models\Appointment;
use App\Models\MedicalConsultation;
use App\Services\SigcmDatabaseService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Throwable;

class AppointmentsRelationManager extends RelationManager
{
    protected static string $relationship = 'appointments';

    protected static bool $isLazy = false;

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('sigcm.patients.appointments_tab');
    }

    public function isReadOnly(): bool
    {
        return false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('starts_at')
            ->modifyQueryUsing(fn (Builder $query): Builder => $query
                ->with(['doctor', 'medicalConsultation'])
                ->withCount('reschedules'))
            ->columns([
                TextColumn::make('id')
                    ->label(__('sigcm.fields.id'))
                    ->formatStateUsing(fn ($state): string => "CITA-#{$state}")
                    ->color('primary')
                    ->url(fn (Appointment $record): string => AppointmentResource::getUrl('view', ['record' => $record]))
                    ->sortable(),
                TextColumn::make('starts_at')
                    ->label(__('sigcm.fields.starts_at'))
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
                TextColumn::make('ends_at')
                    ->label(__('sigcm.fields.ends_at'))
                    ->dateTime('H:i'),
                TextColumn::make('doctor.full_name')
                    ->label(__('sigcm.fields.doctor'))
                    ->searchable(['first_names', 'last_names']),
                TextColumn::make('reason')
                    ->label(__('sigcm.fields.reason'))
                    ->limit(35)
                    ->placeholder('-'),
                TextColumn::make('status')
                    ->label(__('sigcm.fields.status'))
                    ->badge()
                    ->sortable(),
                TextColumn::make('reschedules_count')
                    ->label(__('sigcm.fields.reschedules_count'))
                    ->badge()
                    ->color('gray'),
                TextColumn::make('medicalConsultation.id')
                    ->label(__('sigcm.fields.consultation'))
                    ->badge()
                    ->formatStateUsing(fn ($state): string => $state ? "Consulta #{$state}" : 'Sin consulta')
                    ->color(fn ($state): string => $state ? 'success' : 'gray')
                    ->url(fn (Appointment $record): ?string => $record->medicalConsultation !== null
                        ? MedicalConsultationResource::getUrl('view', ['record' => $record->medicalConsultation])
                        : null)
                    ->placeholder('Sin consulta'),
            ])
            ->defaultSort('starts_at', 'desc')
            ->headerActions([
                Action::make('programarCita')
                    ->label(__('sigcm.actions.new_appointment'))
                    ->icon(Heroicon::OutlinedCalendarDays)
                    ->url(fn (): string => AppointmentResource::getUrl('create', [
                        'patient_id' => $this->getOwnerRecord()->getKey(),
                    ]))
                    ->visible(fn (): bool => auth()->user()?->can('create', Appointment::class) ?? false),
            ])
            ->recordActions([
                Action::make('confirmar')
                    ->label(__('sigcm.actions.confirm'))
                    ->icon(Heroicon::OutlinedCheckCircle)
                    ->color('info')
                    ->visible(fn (Appointment $record): bool => $record->status === AppointmentStatus::Pending)
                    ->action(function (Appointment $record): void {
                        try {
                            app(SigcmDatabaseService::class)->confirmarCita($record, Auth::id());
                            Notification::make()->title('Cita confirmada')->success()->send();
                        } catch (Throwable $exception) {
                            Notification::make()->title('No se pudo confirmar')->body($exception->getMessage())->danger()->send();
                        }
                    }),
                Action::make('enSala')
                    ->label(__('sigcm.actions.check_in'))
                    ->icon(Heroicon::OutlinedCheckBadge)
                    ->color('primary')
                    ->visible(fn (Appointment $record): bool => in_array($record->status, [
                        AppointmentStatus::Pending,
                        AppointmentStatus::Confirmed,
                    ], true))
                    ->action(function (Appointment $record): void {
                        try {
                            app(SigcmDatabaseService::class)->registrarLlegadaCita($record, Auth::id());
                            Notification::make()->title('Paciente registrado en sala')->success()->send();
                        } catch (Throwable $exception) {
                            Notification::make()->title('No se pudo registrar llegada')->body($exception->getMessage())->danger()->send();
                        }
                    }),
                AppointmentResource::makeRescheduleAction(),
                Action::make('atender')
                    ->label(__('sigcm.actions.attend'))
                    ->icon(Heroicon::OutlinedClipboardDocumentCheck)
                    ->color('success')
                    ->visible(fn (Appointment $record): bool => $record->medicalConsultation === null
                        && in_array($record->status, [
                            AppointmentStatus::Pending,
                            AppointmentStatus::Confirmed,
                            AppointmentStatus::CheckedIn,
                        ], true)
                        && (auth()->user()?->can('create', MedicalConsultation::class) ?? false))
                    ->url(fn (Appointment $record): string => MedicalConsultationResource::getUrl('create', [
                        'appointment_id' => $record->id,
                    ])),
                Action::make('verCita')
                    ->label(__('sigcm.actions.view_detail'))
                    ->icon(Heroicon::OutlinedEye)
                    ->url(fn (Appointment $record): string => AppointmentResource::getUrl('view', ['record' => $record])),
            ]);
    }
}
