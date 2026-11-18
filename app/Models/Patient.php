<?php

namespace App\Models;

use Database\Factories\PatientFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'first_names',
    'last_names',
    'document_type',
    'document_number',
    'birth_date',
    'phone',
    'email',
    'is_active',
])]
class Patient extends Model
{
    /** @use HasFactory<PatientFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'birth_date' => 'date',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Nombre completo del paciente.
     */
    protected function fullName(): Attribute
    {
        return Attribute::make(
            get: fn (): string => trim("{$this->first_names} {$this->last_names}"),
        );
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class, 'patient_id');
    }

    public function medicalConsultations(): HasMany
    {
        return $this->hasMany(MedicalConsultation::class, 'patient_id');
    }

    public function clinicalRecord(): HasOne
    {
        return $this->hasOne(ClinicalRecord::class, 'patient_id');
    }

    public function conditions(): HasMany
    {
        return $this->hasMany(PatientCondition::class, 'patient_id');
    }

    public function allergies(): HasMany
    {
        return $this->hasMany(PatientAllergy::class, 'patient_id');
    }

    public function medications(): HasMany
    {
        return $this->hasMany(PatientMedication::class, 'patient_id');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(ClinicalDocument::class, 'patient_id');
    }

    public function followUpPlans(): HasManyThrough
    {
        return $this->hasManyThrough(
            FollowUpPlan::class,
            MedicalConsultation::class,
            'patient_id',
            'originating_consultation_id'
        );
    }

    public function prescriptions(): HasManyThrough
    {
        return $this->hasManyThrough(
            Prescription::class,
            MedicalConsultation::class,
            'patient_id',
            'consultation_id'
        );
    }

    public function clinicalOrders(): HasManyThrough
    {
        return $this->hasManyThrough(
            ClinicalOrder::class,
            MedicalConsultation::class,
            'patient_id',
            'consultation_id'
        );
    }

    public function referrals(): HasManyThrough
    {
        return $this->hasManyThrough(
            Referral::class,
            MedicalConsultation::class,
            'patient_id',
            'consultation_id'
        );
    }
}
