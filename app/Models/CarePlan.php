<?php

namespace App\Models;

use Database\Factories\CarePlanFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'episode_id',
    'created_from_consultation_id',
    'status',
    'objective',
    'created_at',
])]
class CarePlan extends Model
{
    /** @use HasFactory<CarePlanFactory> */
    use HasFactory;

    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    public function episode(): BelongsTo
    {
        return $this->belongsTo(CareEpisode::class, 'episode_id');
    }

    public function originatingConsultation(): BelongsTo
    {
        return $this->belongsTo(MedicalConsultation::class, 'created_from_consultation_id');
    }

    public function activities(): HasMany
    {
        return $this->hasMany(CarePlanActivity::class, 'care_plan_id');
    }
}
