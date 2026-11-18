<?php

namespace App\Models;

use Database\Factories\PatientConditionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'patient_id',
    'code_system',
    'code',
    'display',
    'clinical_status',
    'verification_status',
    'onset_date',
    'resolved_date',
    'created_at',
])]
class PatientCondition extends Model
{
    /** @use HasFactory<PatientConditionFactory> */
    use HasFactory;

    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'onset_date' => 'date',
            'resolved_date' => 'date',
            'created_at' => 'datetime',
        ];
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class, 'patient_id');
    }

    public function consultationDiagnoses(): HasMany
    {
        return $this->hasMany(ConsultationDiagnosis::class, 'patient_condition_id');
    }

    public function careEpisodes(): BelongsToMany
    {
        return $this->belongsToMany(
            CareEpisode::class,
            'episode_conditions',
            'condition_id',
            'episode_id'
        )->withPivot(['created_at']);
    }
}
