<?php

namespace App\Models;

use App\Enums\ConsultationStatus;
use Database\Factories\MedicalConsultationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'patient_id',
    'doctor_id',
    'appointment_id',
    'occurred_at',
    'status',
    'chief_complaint',
    'subjective',
    'objective',
    'assessment',
    'plan',
    'signed_at',
])]
class MedicalConsultation extends Model
{
    /** @use HasFactory<MedicalConsultationFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
            'signed_at' => 'datetime',
            'status' => ConsultationStatus::class,
        ];
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class, 'patient_id');
    }

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(Doctor::class, 'doctor_id');
    }

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class, 'appointment_id');
    }

    public function openedClinicalRecord(): HasOne
    {
        return $this->hasOne(ClinicalRecord::class, 'opened_from_consultation_id');
    }

    public function careEpisodes(): BelongsToMany
    {
        return $this->belongsToMany(
            CareEpisode::class,
            'episode_consultations',
            'consultation_id',
            'episode_id'
        )->withPivot(['relation_type', 'created_at']);
    }

    public function openedEpisodes(): HasMany
    {
        return $this->hasMany(CareEpisode::class, 'opening_consultation_id');
    }

    public function originatedFollowUpPlans(): HasMany
    {
        return $this->hasMany(FollowUpPlan::class, 'originating_consultation_id');
    }

    public function amendments(): HasMany
    {
        return $this->hasMany(ConsultationAmendment::class, 'consultation_id');
    }

    public function diagnoses(): HasMany
    {
        return $this->hasMany(ConsultationDiagnosis::class, 'consultation_id');
    }

    public function observations(): HasMany
    {
        return $this->hasMany(ClinicalObservation::class, 'consultation_id');
    }

    public function prescriptions(): HasMany
    {
        return $this->hasMany(Prescription::class, 'consultation_id');
    }

    public function clinicalOrders(): HasMany
    {
        return $this->hasMany(ClinicalOrder::class, 'consultation_id');
    }

    public function referrals(): HasMany
    {
        return $this->hasMany(Referral::class, 'consultation_id');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(ClinicalDocument::class, 'consultation_id');
    }

    public function carePlans(): HasMany
    {
        return $this->hasMany(CarePlan::class, 'created_from_consultation_id');
    }
}
