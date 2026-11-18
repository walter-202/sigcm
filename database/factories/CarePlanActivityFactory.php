<?php

namespace Database\Factories;

use App\Models\CarePlan;
use App\Models\CarePlanActivity;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CarePlanActivity>
 */
class CarePlanActivityFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'care_plan_id' => CarePlan::factory(),
            'description' => 'Registro diario de presión arterial y dieta hiposódica.',
            'status' => 'pending',
            'due_at' => now()->addDays(14),
            'created_at' => now(),
        ];
    }
}
