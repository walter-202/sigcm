<?php

namespace Database\Factories;

use App\Models\FollowUpPlan;
use App\Models\MedicalConsultation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FollowUpPlan>
 */
class FollowUpPlanFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'originating_consultation_id' => MedicalConsultation::factory()->signed(),
            'episode_id' => null,
            'appointment_id' => null,
            'resulting_consultation_id' => null,
            'due_at' => now()->addDays(7),
            'goal' => 'Reevaluación clínica de control',
            'status' => 'pending',
        ];
    }
}
