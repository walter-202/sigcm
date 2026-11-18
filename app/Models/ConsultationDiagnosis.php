<?php

namespace App\Models;

use Database\Factories\ConsultationDiagnosisFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'consultation_id',
    'patient_condition_id',
    'code_system',
    'code',
    'diagnosis_text',
    'certainty',
    'is_primary',
    'created_at',
])]
class ConsultationDiagnosis extends Model
{
    /** @use HasFactory<ConsultationDiagnosisFactory> */
    use HasFactory;

    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
            'created_at' => 'datetime',
        ];
    }

    public function consultation(): BelongsTo
    {
        return $this->belongsTo(MedicalConsultation::class, 'consultation_id');
    }

    public function patientCondition(): BelongsTo
    {
        return $this->belongsTo(PatientCondition::class, 'patient_condition_id');
    }
}
