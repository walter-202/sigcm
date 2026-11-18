<?php

namespace App\Models;

use App\Enums\EpisodeStatus;
use Database\Factories\CareEpisodeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'clinical_record_id',
    'opening_consultation_id',
    'title',
    'status',
    'opened_at',
    'closed_at',
])]
class CareEpisode extends Model
{
    /** @use HasFactory<CareEpisodeFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'opened_at' => 'datetime',
            'closed_at' => 'datetime',
            'status' => EpisodeStatus::class,
        ];
    }

    public function clinicalRecord(): BelongsTo
    {
        return $this->belongsTo(ClinicalRecord::class, 'clinical_record_id');
    }

    public function openingConsultation(): BelongsTo
    {
        return $this->belongsTo(MedicalConsultation::class, 'opening_consultation_id');
    }

    public function consultations(): BelongsToMany
    {
        return $this->belongsToMany(
            MedicalConsultation::class,
            'episode_consultations',
            'episode_id',
            'consultation_id'
        )->withPivot(['relation_type', 'created_at']);
    }

    public function followUpPlans(): HasMany
    {
        return $this->hasMany(FollowUpPlan::class, 'episode_id');
    }

    public function conditions(): BelongsToMany
    {
        return $this->belongsToMany(
            PatientCondition::class,
            'episode_conditions',
            'episode_id',
            'condition_id'
        )->withPivot(['created_at']);
    }

    public function carePlans(): HasMany
    {
        return $this->hasMany(CarePlan::class, 'episode_id');
    }
}
