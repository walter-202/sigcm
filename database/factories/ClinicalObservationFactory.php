<?php

namespace Database\Factories;

use App\Models\ClinicalObservation;
use App\Models\MedicalConsultation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ClinicalObservation>
 */
class ClinicalObservationFactory extends Factory
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
            'code' => 'heart_rate',
            'value_number' => 76.000,
            'value_text' => null,
            'unit_code' => 'bpm',
            'observed_at' => now()->subMinutes(5),
            'created_at' => now(),
        ];
    }
}
