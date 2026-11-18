<?php

namespace Database\Factories;

use App\Models\MedicalConsultation;
use App\Models\Prescription;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Prescription>
 */
class PrescriptionFactory extends Factory
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
            'supersedes_prescription_id' => null,
            'status' => 'draft',
            'issued_at' => null,
            'notes' => 'Tomar junto con los alimentos.',
            'created_at' => now(),
        ];
    }

    public function issued(): static
    {
        return $this->state(fn (): array => [
            'status' => 'issued',
            'issued_at' => now(),
        ]);
    }
}
