<?php

namespace App\Models;

use Database\Factories\DiagnosticResultItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'diagnostic_report_id',
    'test_code',
    'test_name',
    'result_number',
    'result_text',
    'unit_code',
    'reference_range',
    'created_at',
])]
class DiagnosticResultItem extends Model
{
    /** @use HasFactory<DiagnosticResultItemFactory> */
    use HasFactory;

    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'result_number' => 'decimal:3',
            'created_at' => 'datetime',
        ];
    }

    public function diagnosticReport(): BelongsTo
    {
        return $this->belongsTo(DiagnosticReport::class, 'diagnostic_report_id');
    }
}
