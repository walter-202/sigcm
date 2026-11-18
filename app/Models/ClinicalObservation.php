<?php

namespace App\Models;

use Database\Factories\ClinicalObservationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'consultation_id',
    'code',
    'value_number',
    'value_text',
    'unit_code',
    'observed_at',
    'created_at',
])]
class ClinicalObservation extends Model
{
    /** @use HasFactory<ClinicalObservationFactory> */
    use HasFactory;

    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'value_number' => 'decimal:3',
            'observed_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    public function consultation(): BelongsTo
    {
        return $this->belongsTo(MedicalConsultation::class, 'consultation_id');
    }
}
