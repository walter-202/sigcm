<?php

namespace Database\Factories;

use App\Models\Patient;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Patient>
 */
class PatientFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'first_names' => fake()->firstName(),
            'last_names' => fake()->lastName(),
            'document_type' => 'CI',
            'document_number' => fake()->unique()->numerify('########'),
            'birth_date' => fake()->date('Y-m-d', '-18 years'),
            'phone' => fake()->numerify('7#######'),
            'email' => fake()->unique()->safeEmail(),
            'is_active' => true,
        ];
    }
}
