<?php

namespace App\Models;

use Database\Factories\FollowUpPlanFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'originating_consultation_id',
    'episode_id',
    'appointment_id',
    'resulting_consultation_id',
    'due_at',
    'goal',
    'status',
])]
class FollowUpPlan extends Model
{
    /** @use HasFactory<FollowUpPlanFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'due_at' => 'datetime',
        ];
    }

    public function originatingConsultation(): BelongsTo
    {
        return $this->belongsTo(MedicalConsultation::class, 'originating_consultation_id');
    }

    public function episode(): BelongsTo
    {
        return $this->belongsTo(CareEpisode::class, 'episode_id');
    }

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class, 'appointment_id');
    }

    public function resultingConsultation(): BelongsTo
    {
        return $this->belongsTo(MedicalConsultation::class, 'resulting_consultation_id');
    }
}
