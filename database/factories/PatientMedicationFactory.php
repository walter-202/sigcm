<?php

namespace Database\Factories;

use App\Models\Patient;
use App\Models\PatientMedication;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PatientMedication>
 */
class PatientMedicationFactory extends Factory
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
            'medication_name' => fake()->randomElement(['Losartán 50 mg', 'Metformina 850 mg', 'Omeprazol 20 mg']),
            'dose' => '1 comprimido',
            'frequency' => 'Cada 12 horas',
            'status' => 'active',
            'source' => 'patient_report',
            'recorded_at' => now(),
        ];
    }
}
