<?php

namespace Database\Factories;

use App\Models\Patient;
use App\Models\PatientAllergy;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PatientAllergy>
 */
class PatientAllergyFactory extends Factory
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
            'substance' => fake()->randomElement(['Penicilina', 'Ibuprofeno', 'Sulfamidas', 'Mariscos']),
            'reaction' => fake()->randomElement(['Urticaria generalizada', 'Broncoespasmo leve', 'Edema labial']),
            'severity' => fake()->randomElement(['mild', 'moderate', 'severe']),
            'verification_status' => 'confirmed',
            'clinical_status' => 'active',
            'created_at' => now(),
        ];
    }
}
