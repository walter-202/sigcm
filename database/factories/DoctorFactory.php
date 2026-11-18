<?php

namespace Database\Factories;

use App\Models\Doctor;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Doctor>
 */
class DoctorFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => null,
            'first_names' => fake()->firstName(),
            'last_names' => fake()->lastName(),
            'professional_license' => 'CMP-'.fake()->unique()->numerify('######'),
            'specialty' => fake()->randomElement(['Medicina general', 'Pediatría', 'Cardiología', 'Medicina interna']),
            'is_active' => true,
        ];
    }

    public function withUser(): static
    {
        return $this->state(fn (): array => [
            'user_id' => User::factory(),
        ]);
    }
}
