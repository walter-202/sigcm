<?php

namespace App\Models;

use Database\Factories\ClinicalRecordFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

#[Fillable([
    'patient_id',
    'opened_from_consultation_id',
    'opened_at',
    'medical_history',
    'surgical_history',
    'family_history',
    'social_history',
])]
class ClinicalRecord extends Model
{
    /** @use HasFactory<ClinicalRecordFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'opened_at' => 'datetime',
        ];
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class, 'patient_id');
    }

    public function openingConsultation(): BelongsTo
    {
        return $this->belongsTo(MedicalConsultation::class, 'opened_from_consultation_id');
    }

    public function careEpisodes(): HasMany
    {
        return $this->hasMany(CareEpisode::class, 'clinical_record_id');
    }

    public function followUpPlans(): HasManyThrough
    {
        return $this->hasManyThrough(
            FollowUpPlan::class,
            MedicalConsultation::class,
            'patient_id',
            'originating_consultation_id',
            'patient_id',
            'id'
        );
    }
}
