<?php

namespace App\Filament\Resources\Patients\Schemas;

use App\Enums\AppointmentStatus;
use App\Enums\ConsultationStatus;
use App\Enums\EpisodeStatus;
use App\Filament\Resources\Appointments\AppointmentResource;
use App\Filament\Resources\ClinicalRecords\ClinicalRecordResource;
use App\Filament\Resources\MedicalConsultations\MedicalConsultationResource;
use App\Models\Appointment;
use App\Models\CareEpisode;
use App\Models\MedicalConsultation;
use App\Models\Patient;
use App\Models\PatientCondition;
use Filament\Actions\Action;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class PatientInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Grid::make(2)
                    ->schema([
                        Section::make(__('sigcm.patients.cards.last_consultation'))
                            ->columns(2)
                            ->schema([
                                TextEntry::make('last_consultation_date')
                                    ->label(__('sigcm.fields.last_consultation_date'))
                                    ->state(fn (Patient $record): string => self::latestConsultation($record)?->occurred_at?->format('d/m/Y H:i') ?? 'Sin consultas registradas'),
                                TextEntry::make('last_consultation_status')
                                    ->label(__('sigcm.fields.status'))
                                    ->badge()
                                    ->state(function (Patient $record): string {
                                        $consultation = self::latestConsultation($record);

                                        return $consultation?->status instanceof ConsultationStatus
                                            ? $consultation->status->getLabel()
                                            : 'Sin registro';
                                    })
                                    ->color(fn (Patient $record): string => self::latestConsultation($record)?->status === ConsultationStatus::Signed ? 'success' : 'gray'),
                                TextEntry::make('last_consultation_reason')
                                    ->label(__('sigcm.fields.reason'))
                                    ->state(fn (Patient $record): string => self::latestConsultation($record)?->chief_complaint ?? '-')
                                    ->columnSpanFull(),
                                TextEntry::make('last_consultation_doctor')
                                    ->label(__('sigcm.fields.doctor'))
                                    ->state(fn (Patient $record): string => self::latestConsultation($record)?->doctor?->full_name ?? '-'),
                                TextEntry::make('last_consultation_link')
                                    ->label(__('sigcm.fields.consultation'))
                                    ->state(fn (Patient $record): string => ($consultation = self::latestConsultation($record)) ? "Ver consulta #{$consultation->id}" : '-')
                                    ->color('primary')
                                    ->url(fn (Patient $record): ?string => ($consultation = self::latestConsultation($record))
                                        ? MedicalConsultationResource::getUrl('view', ['record' => $consultation])
                                        : null),
                            ]),

                        Section::make(__('sigcm.patients.cards.next_appointment'))
                            ->columns(2)
                            ->schema([
                                TextEntry::make('next_appointment_date')
                                    ->label(__('sigcm.fields.next_appointment_date'))
                                    ->state(fn (Patient $record): string => self::nextAppointment($record)?->starts_at?->format('d/m/Y H:i') ?? 'Sin citas activas'),
                                TextEntry::make('next_appointment_status')
                                    ->label(__('sigcm.fields.status'))
                                    ->badge()
                                    ->state(function (Patient $record): string {
                                        $appointment = self::nextAppointment($record);

                                        return $appointment?->status instanceof AppointmentStatus
                                            ? $appointment->status->getLabel()
                                            : 'Sin cita';
                                    })
                                    ->color(fn (Patient $record): string => self::nextAppointment($record)?->status?->getColor() ?? 'gray'),
                                TextEntry::make('next_appointment_reason')
                                    ->label(__('sigcm.fields.reason'))
                                    ->state(fn (Patient $record): string => self::nextAppointment($record)?->reason ?? '-')
                                    ->columnSpanFull(),
                                TextEntry::make('next_appointment_doctor')
                                    ->label(__('sigcm.fields.doctor'))
                                    ->state(fn (Patient $record): string => self::nextAppointment($record)?->doctor?->full_name ?? '-'),
                                TextEntry::make('next_appointment_link')
                                    ->label(__('sigcm.fields.detail'))
                                    ->state(fn (Patient $record): string => ($appointment = self::nextAppointment($record)) ? "Ver CITA-#{$appointment->id}" : '-')
                                    ->color('primary')
                                    ->url(fn (Patient $record): ?string => ($appointment = self::nextAppointment($record))
                                        ? AppointmentResource::getUrl('view', ['record' => $appointment])
                                        : null),
                            ]),
                    ])
                    ->columnSpanFull(),

                Section::make(__('sigcm.patients.cards.clinical_record'))
                    ->description('Episodios de atención, condiciones vinculadas y antecedentes consolidados desde las consultas firmadas.')
                    ->headerActions([
                        Action::make('abrirExpediente')
                            ->label(__('sigcm.actions.open_clinical_record'))
                            ->icon(Heroicon::OutlinedFolderOpen)
                            ->visible(fn (Patient $record): bool => $record->clinicalRecord !== null)
                            ->url(fn (Patient $record): ?string => $record->clinicalRecord
                                ? ClinicalRecordResource::getUrl('view', ['record' => $record->clinicalRecord])
                                : null),
                    ])
                    ->columns(2)
                    ->schema([
                        TextEntry::make('clinicalRecord.medical_history')
                            ->label(__('sigcm.fields.medical_history'))
                            ->placeholder('Sin antecedentes registrados'),
                        TextEntry::make('clinicalRecord.surgical_history')
                            ->label(__('sigcm.fields.surgical_history'))
                            ->placeholder('Sin antecedentes quirúrgicos'),
                        TextEntry::make('clinicalRecord.family_history')
                            ->label(__('sigcm.fields.family_history'))
                            ->placeholder('Sin antecedentes familiares'),
                        TextEntry::make('clinicalRecord.social_history')
                            ->label(__('sigcm.fields.social_history'))
                            ->placeholder('Sin antecedentes sociales'),
                        RepeatableEntry::make('clinicalRecord.careEpisodes')
                            ->label(__('sigcm.patients.cards.recent_activity'))
                            ->columns(3)
                            ->schema([
                                TextEntry::make('title')
                                    ->label(__('sigcm.fields.episode'))
                                    ->color('primary')
                                    ->url(fn (CareEpisode $record): string => ClinicalRecordResource::getUrl('view', ['record' => $record->clinical_record_id])),
                                TextEntry::make('status')
                                    ->label(__('sigcm.fields.status'))
                                    ->badge()
                                    ->formatStateUsing(fn ($state): string => $state instanceof EpisodeStatus ? $state->getLabel() : (string) $state),
                                TextEntry::make('opened_at')
                                    ->label(__('sigcm.fields.opened_at'))
                                    ->dateTime('d/m/Y H:i'),
                                TextEntry::make('linked_conditions')
                                    ->label(__('sigcm.patients.conditions_tab'))
                                    ->state(fn (CareEpisode $record): string => $record->conditions->isEmpty()
                                        ? 'Sin condiciones asociadas'
                                        : $record->conditions->map(fn (PatientCondition $c): string => ($c->code ? "{$c->code} · " : '').$c->display)->implode(', ')),
                                TextEntry::make('linked_consultations')
                                    ->label(__('sigcm.fields.consultation'))
                                    ->state(fn (CareEpisode $record): string => $record->consultations->isEmpty()
                                        ? "Consulta #{$record->opening_consultation_id}"
                                        : $record->consultations->map(fn (MedicalConsultation $c): string => "#{$c->id} ({$c->pivot?->relation_type})")->implode(', '))
                                    ->columnSpan(2),
                            ])
                            ->placeholder('La historia clínica se abrirá automáticamente al firmar la primera consulta.')
                            ->columnSpanFull(),
                    ])
                    ->columnSpanFull(),
            ]);
    }

    private static function latestConsultation(Patient $record): ?MedicalConsultation
    {
        return $record->medicalConsultations()
            ->with('doctor')
            ->orderByDesc('occurred_at')
            ->first();
    }

    private static function nextAppointment(Patient $record): ?Appointment
    {
        return $record->appointments()
            ->with('doctor')
            ->whereIn('status', [
                AppointmentStatus::Pending->value,
                AppointmentStatus::Confirmed->value,
                AppointmentStatus::CheckedIn->value,
            ])
            ->orderBy('starts_at')
            ->first();
    }
}
