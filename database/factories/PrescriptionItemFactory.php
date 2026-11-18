<?php

namespace Database\Factories;

use App\Models\Prescription;
use App\Models\PrescriptionItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PrescriptionItem>
 */
class PrescriptionItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'prescription_id' => Prescription::factory(),
            'medication_name' => 'Enalapril 10 mg',
            'dose' => '1 comprimido',
            'route' => 'Vía oral',
            'frequency' => 'Cada 12 horas',
            'duration_days' => 30,
            'quantity' => 60.00,
            'instructions' => 'Controlar presión arterial diariamente.',
            'created_at' => now(),
        ];
    }
}
