<?php

namespace Database\Factories;

use App\Models\ClinicalRecord;
use App\Models\MedicalConsultation;
use App\Models\Patient;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ClinicalRecord>
 */
class ClinicalRecordFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'patient_id' => Patient::factory(),
            'opened_from_consultation_id' => function (array $attributes): int {
                return MedicalConsultation::factory()
                    ->signed()
                    ->create(['patient_id' => $attributes['patient_id']])
                    ->id;
            },
            'opened_at' => now(),
            'medical_history' => 'Sin antecedentes patológicos relevantes.',
            'surgical_history' => 'Niega cirugías previas.',
            'family_history' => 'Sin antecedentes hereditarios destacados.',
            'social_history' => 'Sin hábitos tóxicos declarados.',
        ];
    }
}
