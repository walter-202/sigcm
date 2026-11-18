<?php

namespace App\Filament\Resources\MedicalConsultations\Schemas;

use App\Filament\Resources\Appointments\AppointmentResource;
use App\Filament\Resources\Patients\PatientResource;
use App\Models\MedicalConsultation;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class MedicalConsultationInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('sigcm.consultation.sections.header'))
                    ->columns(3)
                    ->schema([
                        TextEntry::make('id')
                            ->label(__('sigcm.fields.consultation_id'))
                            ->formatStateUsing(fn ($state): string => "#{$state}"),
                        TextEntry::make('status')
                            ->label(__('sigcm.fields.status'))
                            ->badge(),
                        TextEntry::make('occurred_at')
                            ->label(__('sigcm.fields.occurred_at'))
                            ->dateTime('d/m/Y H:i'),
                        TextEntry::make('patient.full_name')
                            ->label(__('sigcm.fields.patient'))
                            ->color('primary')
                            ->url(fn (MedicalConsultation $record): string => PatientResource::getUrl('view', ['record' => $record->patient_id])),
                        TextEntry::make('doctor.full_name')
                            ->label(__('sigcm.fields.attending_doctor')),
                        TextEntry::make('appointment.id')
                            ->label(__('sigcm.fields.appointment'))
                            ->formatStateUsing(fn ($state): string => $state ? "CITA-#{$state}" : 'Atención directa')
                            ->url(fn (MedicalConsultation $record): ?string => $record->appointment_id
                                ? AppointmentResource::getUrl('view', ['record' => $record->appointment_id])
                                : null)
                            ->placeholder('Atención directa'),
                        TextEntry::make('signed_at')
                            ->label(__('sigcm.fields.signed_at'))
                            ->dateTime('d/m/Y H:i')
                            ->placeholder('Pendiente de firma'),
                    ])
                    ->columnSpanFull(),

                Section::make(__('sigcm.consultation.sections.clinical_note'))
                    ->schema([
                        TextEntry::make('chief_complaint')
                            ->label(__('sigcm.consultation.sections.anamnesis_label'))
                            ->placeholder('-')
                            ->columnSpanFull(),
                        TextEntry::make('subjective')
                            ->label(__('sigcm.consultation.sections.subjective_label'))
                            ->placeholder('-')
                            ->columnSpanFull(),
                        TextEntry::make('objective')
                            ->label(__('sigcm.consultation.sections.physical_exam_label'))
                            ->placeholder('-')
                            ->columnSpanFull(),
                        TextEntry::make('assessment')
                            ->label(__('sigcm.consultation.sections.diagnostic_impression_label'))
                            ->placeholder('-')
                            ->columnSpanFull(),
                        TextEntry::make('plan')
                            ->label(__('sigcm.consultation.sections.management_plan_label'))
                            ->placeholder('-')
                            ->columnSpanFull(),
                    ])
                    ->columnSpanFull(),

                Section::make(__('sigcm.consultation.sections.vitals'))
                    ->schema([
                        RepeatableEntry::make('observations')
                            ->label(__('sigcm.fields.vitals_entries'))
                            ->columns(4)
                            ->schema([
                                TextEntry::make('code')
                                    ->label(__('sigcm.fields.parameter'))
                                    ->badge(),
                                TextEntry::make('value_display')
                                    ->label(__('sigcm.fields.value'))
                                    ->state(fn ($record): string => $record->value_text ?? (string) $record->value_number),
                                TextEntry::make('unit_code')
                                    ->label(__('sigcm.fields.unit'))
                                    ->placeholder('-'),
                                TextEntry::make('observed_at')
                                    ->label(__('sigcm.fields.observed_at'))
                                    ->dateTime('d/m/Y H:i'),
                            ])
                            ->placeholder('Sin signos vitales registrados.'),
                    ])
                    ->columnSpanFull(),

                Section::make(__('sigcm.consultation.sections.diagnoses'))
                    ->schema([
                        RepeatableEntry::make('diagnoses')
                            ->label(__('sigcm.fields.diagnoses_entries'))
                            ->columns(4)
                            ->schema([
                                TextEntry::make('code')
                                    ->label(__('sigcm.fields.code'))
                                    ->badge()
                                    ->formatStateUsing(fn (?string $state, $record): string => $state ? "{$record->code_system}: {$state}" : 'Sin código'),
                                TextEntry::make('diagnosis_text')
                                    ->label(__('sigcm.fields.diagnosis_text')),
                                TextEntry::make('certainty')
                                    ->label(__('sigcm.fields.certainty'))
                                    ->formatStateUsing(fn (string $state): string => match ($state) {
                                        'confirmed' => 'Confirmado',
                                        'ruled_out' => 'Descartado',
                                        default => 'Presuntivo',
                                    }),
                                IconEntry::make('is_primary')
                                    ->label(__('sigcm.fields.is_primary'))
                                    ->boolean(),
                            ])
                            ->placeholder('Sin diagnósticos codificados.'),
                    ])
                    ->columnSpanFull(),
            ]);
    }
}
