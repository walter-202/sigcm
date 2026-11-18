<?php

namespace Database\Factories;

use App\Models\ClinicalOrder;
use App\Models\MedicalConsultation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ClinicalOrder>
 */
class ClinicalOrderFactory extends Factory
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
            'order_type' => fake()->randomElement(['lab', 'imaging', 'procedure', 'other']),
            'title' => fake()->randomElement([
                'Hemograma completo y química sanguínea',
                'Perfil lipídico y glucemia en ayunas',
                'Electrocardiograma de 12 derivaciones',
            ]),
            'status' => 'ordered',
            'priority' => 'routine',
            'ordered_at' => now(),
            'created_at' => now(),
        ];
    }
}
