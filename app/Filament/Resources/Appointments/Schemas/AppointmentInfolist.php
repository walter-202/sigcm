<?php

namespace App\Filament\Resources\Appointments\Schemas;

use App\Enums\ConsultationStatus;
use App\Filament\Resources\Doctors\DoctorResource;
use App\Filament\Resources\MedicalConsultations\MedicalConsultationResource;
use App\Filament\Resources\Patients\PatientResource;
use App\Models\Appointment;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class AppointmentInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('sigcm.schedule.appointment_detail'))
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema([
                        TextEntry::make('id')
                            ->label(__('sigcm.fields.id'))
                            ->numeric(),
                        TextEntry::make('status')
                            ->label(__('sigcm.fields.status'))
                            ->badge(),
                        TextEntry::make('patient.full_name')
                            ->label(__('sigcm.fields.patient'))
                            ->color('primary')
                            ->url(fn (Appointment $record): string => PatientResource::getUrl('view', ['record' => $record->patient_id])),
                        TextEntry::make('doctor.full_name')
                            ->label(__('sigcm.fields.doctor'))
                            ->color('primary')
                            ->url(fn (Appointment $record): string => DoctorResource::getUrl('view', ['record' => $record->doctor_id])),
                        TextEntry::make('starts_at')
                            ->label(__('sigcm.fields.starts_at'))
                            ->dateTime('d/m/Y H:i'),
                        TextEntry::make('ends_at')
                            ->label(__('sigcm.fields.ends_at'))
                            ->dateTime('d/m/Y H:i'),
                        TextEntry::make('createdByUser.name')
                            ->label(__('sigcm.fields.created_by'))
                            ->placeholder('-'),
                        TextEntry::make('medicalConsultation.id')
                            ->label(__('sigcm.fields.consultation'))
                            ->badge()
                            ->formatStateUsing(function ($state, Appointment $record): string {
                                if (! $state || $record->medicalConsultation === null) {
                                    return 'Sin consulta';
                                }

                                $statusLabel = $record->medicalConsultation->status instanceof ConsultationStatus
                                    ? $record->medicalConsultation->status->getLabel()
                                    : (string) $record->medicalConsultation->status;

                                return "Consulta #{$state} · {$statusLabel}";
                            })
                            ->color(function ($state, Appointment $record): string {
                                if (! $state || $record->medicalConsultation === null) {
                                    return 'gray';
                                }

                                return $record->medicalConsultation->status === ConsultationStatus::Signed
                                    ? 'success'
                                    : 'warning';
                            })
                            ->url(fn (Appointment $record): ?string => $record->medicalConsultation !== null
                                ? MedicalConsultationResource::getUrl('view', ['record' => $record->medicalConsultation])
                                : null)
                            ->placeholder('Sin consulta'),
                        TextEntry::make('reason')
                            ->label(__('sigcm.fields.reason'))
                            ->placeholder('-')
                            ->columnSpanFull(),
                        TextEntry::make('cancellation_reason')
                            ->label(__('sigcm.fields.cancellation_reason'))
                            ->placeholder('-')
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
