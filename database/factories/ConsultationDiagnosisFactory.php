<?php

namespace Database\Factories;

use App\Models\ConsultationDiagnosis;
use App\Models\MedicalConsultation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ConsultationDiagnosis>
 */
class ConsultationDiagnosisFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'consultation_id' => MedicalConsultation::factory(),
            'patient_condition_id' => null,
            'code_system' => 'CIE-10',
            'code' => 'I10',
            'diagnosis_text' => 'Hipertensión arterial esencial',
            'certainty' => 'confirmed',
            'is_primary' => true,
            'created_at' => now(),
        ];
    }
}
