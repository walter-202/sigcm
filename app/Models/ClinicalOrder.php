<?php

namespace App\Models;

use Database\Factories\ClinicalOrderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'consultation_id',
    'order_type',
    'title',
    'status',
    'priority',
    'ordered_at',
    'created_at',
])]
class ClinicalOrder extends Model
{
    /** @use HasFactory<ClinicalOrderFactory> */
    use HasFactory;

    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'ordered_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    public function consultation(): BelongsTo
    {
        return $this->belongsTo(MedicalConsultation::class, 'consultation_id');
    }

    public function diagnosticReports(): HasMany
    {
        return $this->hasMany(DiagnosticReport::class, 'clinical_order_id');
    }
}
