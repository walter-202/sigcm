<?php

namespace App\Filament\Resources\MedicalConsultations\Schemas;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\Doctor;
use App\Models\MedicalConsultation;
use App\Models\Patient;
use App\Models\PatientAllergy;
use App\Models\PatientCondition;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;

class MedicalConsultationForm
{
    /**
     * @var array<string, array{label: string, unit: string, numeric: bool}>
     */
    private const OBSERVATION_CATALOG = [
        'blood_pressure' => ['label' => 'Presión arterial (PA)', 'unit' => 'mmHg', 'numeric' => false],
        'heart_rate' => ['label' => 'Frecuencia cardíaca (FC)', 'unit' => 'lpm', 'numeric' => true],
        'respiratory_rate' => ['label' => 'Frecuencia respiratoria (FR)', 'unit' => 'rpm', 'numeric' => true],
        'temperature' => ['label' => 'Temperatura corporal', 'unit' => '°C', 'numeric' => true],
        'spo2' => ['label' => 'Saturación de oxígeno (SpO2)', 'unit' => '%', 'numeric' => true],
        'weight' => ['label' => 'Peso', 'unit' => 'kg', 'numeric' => true],
        'height' => ['label' => 'Talla', 'unit' => 'cm', 'numeric' => true],
    ];

    public static function configure(Schema $schema): Schema
    {
        $prefillAppointmentId = request()->integer('appointment_id') ?: null;
        $prefillAppointment = $prefillAppointmentId ? Appointment::query()->find($prefillAppointmentId) : null;
        $prefillPatientId = request()->integer('patient_id') ?: $prefillAppointment?->patient_id;
        $prefillDoctorId = $prefillAppointment?->doctor_id;

        return $schema
            ->components([
                Section::make(__('sigcm.consultation.sections.context'))
                    ->columns(2)
                    ->schema([
                        Select::make('appointment_id')
                            ->label(__('sigcm.fields.optional_appointment'))
                            ->relationship(
                                name: 'appointment',
                                titleAttribute: 'id',
                                modifyQueryUsing: fn (Builder $query, ?MedicalConsultation $record): Builder => $query
                                    ->with(['patient', 'doctor'])
                                    ->where(function (Builder $sub) use ($record): void {
                                        $sub->whereDoesntHave('medicalConsultation');
                                        if ($record?->appointment_id !== null) {
                                            $sub->orWhere('id', $record->appointment_id);
                                        }
                                    })
                                    ->whereNotIn('status', [
                                        AppointmentStatus::Cancelled->value,
                                        AppointmentStatus::NoShow->value,
                                    ])
                            )
                            ->getOptionLabelFromRecordUsing(fn (Appointment $record): string => "CITA-#{$record->id} · {$record->patient?->full_name} ({$record->starts_at?->format('d/m/Y H:i')})")
                            ->searchable()
                            ->preload()
                            ->default($prefillAppointmentId)
                            ->live()
                            ->afterStateUpdated(function ($state, Set $set): void {
                                if (blank($state)) {
                                    return;
                                }

                                $appointment = Appointment::query()->find($state);
                                if ($appointment !== null) {
                                    $set('patient_id', $appointment->patient_id);
                                    $set('doctor_id', $appointment->doctor_id);
                                }
                            })
                            ->nullable(),
                        DateTimePicker::make('occurred_at')
                            ->label(__('sigcm.fields.occurred_at'))
                            ->default(now())
                            ->seconds(false)
                            ->required(),
                        Select::make('patient_id')
                            ->label(__('sigcm.fields.patient'))
                            ->relationship('patient', 'first_names')
                            ->getOptionLabelFromRecordUsing(fn (Patient $record): string => $record->full_name)
                            ->searchable(['first_names', 'last_names', 'document_number'])
                            ->preload()
                            ->default($prefillPatientId)
                            ->live()
                            ->required(),
                        Select::make('doctor_id')
                            ->label(__('sigcm.fields.attending_doctor'))
                            ->relationship('doctor', 'first_names')
                            ->getOptionLabelFromRecordUsing(fn (Doctor $record): string => "{$record->full_name} ({$record->professional_license})")
                            ->searchable(['first_names', 'last_names', 'professional_license'])
                            ->preload()
                            ->default($prefillDoctorId)
                            ->required(),
                        Placeholder::make('patient_safety_summary')
                            ->label(__('sigcm.fields.patient_alerts'))
                            ->content(fn (Get $get): HtmlString => self::renderPatientAlerts($get('patient_id')))
                            ->columnSpanFull(),
                    ])
                    ->columnSpanFull(),

                Grid::make(12)
                    ->schema([
                        Section::make(__('sigcm.consultation.sections.clinical_note'))
                            ->schema([
                                Textarea::make('chief_complaint')
                                    ->label(__('sigcm.consultation.sections.anamnesis_label'))
                                    ->rows(2)
                                    ->required()
                                    ->columnSpanFull(),
                                Textarea::make('subjective')
                                    ->label(__('sigcm.consultation.sections.subjective_label'))
                                    ->rows(3)
                                    ->columnSpanFull(),
                                Textarea::make('objective')
                                    ->label(__('sigcm.consultation.sections.physical_exam_label'))
                                    ->rows(3)
                                    ->columnSpanFull(),
                                Textarea::make('assessment')
                                    ->label(__('sigcm.consultation.sections.diagnostic_impression_label'))
                                    ->rows(3)
                                    ->columnSpanFull(),
                                Textarea::make('plan')
                                    ->label(__('sigcm.consultation.sections.management_plan_label'))
                                    ->rows(3)
                                    ->columnSpanFull(),
                            ])
                            ->columnSpan(['default' => 12, 'lg' => 7]),

                        Grid::make(1)
                            ->schema([
                                Section::make(__('sigcm.consultation.sections.vitals'))
                                    ->schema([
                                        Repeater::make('observations')
                                            ->label(__('sigcm.fields.vitals_entries'))
                                            ->relationship('observations')
                                            ->defaultItems(0)
                                            ->addActionLabel('Agregar signo vital')
                                            ->columns(2)
                                            ->schema([
                                                Select::make('code')
                                                    ->label(__('sigcm.fields.parameter'))
                                                    ->options(collect(self::OBSERVATION_CATALOG)
                                                        ->mapWithKeys(fn (array $meta, string $code): array => [$code => $meta['label']])
                                                        ->all())
                                                    ->live()
                                                    ->afterStateUpdated(function (?string $state, Set $set): void {
                                                        $meta = self::OBSERVATION_CATALOG[$state ?? ''] ?? null;
                                                        $set('unit_code', $meta['unit'] ?? null);
                                                        if ($meta !== null && $meta['numeric']) {
                                                            $set('value_text', null);
                                                        } else {
                                                            $set('value_number', null);
                                                        }
                                                    })
                                                    ->required(),
                                                TextInput::make('unit_code')
                                                    ->label(__('sigcm.fields.unit'))
                                                    ->maxLength(32),
                                                TextInput::make('value_text')
                                                    ->label(__('sigcm.fields.vitals_value_text'))
                                                    ->visible(fn (Get $get): bool => ! (self::OBSERVATION_CATALOG[$get('code') ?? '']['numeric'] ?? true))
                                                    ->required(fn (Get $get): bool => ! (self::OBSERVATION_CATALOG[$get('code') ?? '']['numeric'] ?? true))
                                                    ->maxLength(255)
                                                    ->columnSpanFull(),
                                                TextInput::make('value_number')
                                                    ->label(__('sigcm.fields.vitals_value_number'))
                                                    ->numeric()
                                                    ->step('0.001')
                                                    ->visible(fn (Get $get): bool => self::OBSERVATION_CATALOG[$get('code') ?? '']['numeric'] ?? true)
                                                    ->required(fn (Get $get): bool => self::OBSERVATION_CATALOG[$get('code') ?? '']['numeric'] ?? true)
                                                    ->columnSpanFull(),
                                                Hidden::make('observed_at')
                                                    ->default(fn () => now()->toDateTimeString()),
                                            ])
                                            ->mutateRelationshipDataBeforeCreateUsing(fn (array $data): array => self::normalizeObservationData($data))
                                            ->mutateRelationshipDataBeforeSaveUsing(fn (array $data): array => self::normalizeObservationData($data)),
                                    ]),

                                Section::make(__('sigcm.consultation.sections.diagnoses'))
                                    ->schema([
                                        Repeater::make('diagnoses')
                                            ->label(__('sigcm.fields.diagnoses_entries'))
                                            ->relationship('diagnoses')
                                            ->defaultItems(0)
                                            ->addActionLabel('Agregar diagnóstico')
                                            ->columns(2)
                                            ->schema([
                                                TextInput::make('code_system')
                                                    ->label(__('sigcm.fields.code_system'))
                                                    ->default('CIE-10')
                                                    ->maxLength(70),
                                                TextInput::make('code')
                                                    ->label(__('sigcm.fields.code'))
                                                    ->maxLength(70),
                                                TextInput::make('diagnosis_text')
                                                    ->label(__('sigcm.fields.diagnosis_text'))
                                                    ->required()
                                                    ->maxLength(255)
                                                    ->columnSpanFull(),
                                                Select::make('certainty')
                                                    ->label(__('sigcm.fields.certainty'))
                                                    ->options([
                                                        'suspected' => 'Presuntivo',
                                                        'confirmed' => 'Confirmado',
                                                        'ruled_out' => 'Descartado',
                                                    ])
                                                    ->default('confirmed')
                                                    ->required(),
                                                Toggle::make('is_primary')
                                                    ->label(__('sigcm.fields.is_primary'))
                                                    ->inline(false)
                                                    ->default(false),
                                                Select::make('patient_condition_id')
                                                    ->label(__('sigcm.fields.patient_condition_link'))
                                                    ->options(function (Get $get): array {
                                                        $patientId = $get('../../patient_id');
                                                        if (blank($patientId)) {
                                                            return [];
                                                        }

                                                        return PatientCondition::query()
                                                            ->where('patient_id', $patientId)
                                                            ->orderBy('display')
                                                            ->pluck('display', 'id')
                                                            ->all();
                                                    })
                                                    ->searchable()
                                                    ->nullable()
                                                    ->columnSpanFull(),
                                            ])
                                            ->mutateRelationshipDataBeforeCreateUsing(fn (array $data): array => [
                                                ...$data,
                                                'created_at' => now(),
                                            ]),
                                    ]),
                            ])
                            ->columnSpan(['default' => 12, 'lg' => 5]),
                    ])
                    ->columnSpanFull(),
            ]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private static function normalizeObservationData(array $data): array
    {
        $code = (string) ($data['code'] ?? '');
        $meta = self::OBSERVATION_CATALOG[$code] ?? ['unit' => null, 'numeric' => true];

        if ($meta['numeric']) {
            $data['value_text'] = null;
        } else {
            $data['value_number'] = null;
        }

        $data['unit_code'] = $data['unit_code'] ?: $meta['unit'];
        $data['observed_at'] = $data['observed_at'] ?? now();
        $data['created_at'] = $data['created_at'] ?? now();

        return $data;
    }

    private static function renderPatientAlerts(mixed $patientId): HtmlString
    {
        if (blank($patientId)) {
            return new HtmlString('<span style="font-size:0.875rem;opacity:0.75;">Seleccione un paciente para consultar alergias activas y número de historia clínica.</span>');
        }

        $patient = Patient::query()->with('clinicalRecord')->find($patientId);
        if ($patient === null) {
            return new HtmlString('<span style="font-size:0.875rem;opacity:0.75;">-</span>');
        }

        $hc = $patient->clinicalRecord !== null
            ? 'HC-'.str_pad((string) $patient->clinicalRecord->id, 4, '0', STR_PAD_LEFT)
            : 'Sin historia abierta';

        $allergies = PatientAllergy::query()
            ->where('patient_id', $patient->id)
            ->where('clinical_status', 'active')
            ->get(['substance', 'severity']);

        $allergyText = $allergies->isEmpty()
            ? 'Sin alergias activas registradas'
            : $allergies->map(fn (PatientAllergy $allergy): string => e($allergy->substance).($allergy->severity ? ' ('.e(__("enums.allergy_severity.{$allergy->severity}")).')' : ''))->implode(', ');

        $allergyStyle = $allergies->isEmpty()
            ? 'opacity:0.85;'
            : 'font-weight:600;color:#ef4444;';

        return new HtmlString("<div style=\"font-size:0.875rem;display:flex;flex-direction:column;gap:0.25rem;\"><div><strong>Expediente:</strong> {$hc}</div><div style=\"{$allergyStyle}\"><strong>Alergias activas:</strong> {$allergyText}</div></div>");
    }
}
