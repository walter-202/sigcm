<?php

namespace App\Models;

use Database\Factories\DiagnosticReportFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'clinical_order_id',
    'reviewed_by_user_id',
    'status',
    'interpretation',
    'issued_at',
    'reviewed_at',
    'created_at',
])]
class DiagnosticReport extends Model
{
    /** @use HasFactory<DiagnosticReportFactory> */
    use HasFactory;

    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'issued_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    public function clinicalOrder(): BelongsTo
    {
        return $this->belongsTo(ClinicalOrder::class, 'clinical_order_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_user_id');
    }

    public function resultItems(): HasMany
    {
        return $this->hasMany(DiagnosticResultItem::class, 'diagnostic_report_id');
    }
}
