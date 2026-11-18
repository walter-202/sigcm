<?php

namespace Database\Factories;

use App\Models\Patient;
use App\Models\PatientCondition;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PatientCondition>
 */
class PatientConditionFactory extends Factory
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
            'code_system' => 'CIE-10',
            'code' => fake()->randomElement(['I10', 'E11', 'J45', 'K21']),
            'display' => fake()->randomElement([
                'Hipertensión arterial esencial',
                'Diabetes mellitus tipo 2',
                'Asma bronquial controlada',
                'Enfermedad por reflujo gastroesofágico',
            ]),
            'clinical_status' => 'active',
            'verification_status' => 'confirmed',
            'onset_date' => now()->subMonths(6)->toDateString(),
            'resolved_date' => null,
            'created_at' => now(),
        ];
    }
}
