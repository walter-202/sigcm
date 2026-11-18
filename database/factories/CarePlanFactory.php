<?php

namespace Database\Factories;

use App\Models\CareEpisode;
use App\Models\CarePlan;
use App\Models\MedicalConsultation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CarePlan>
 */
class CarePlanFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'episode_id' => CareEpisode::factory(),
            'created_from_consultation_id' => function (array $attributes): int {
                $episode = CareEpisode::query()->find($attributes['episode_id']);

                if ($episode !== null) {
                    return (int) $episode->opening_consultation_id;
                }

                return MedicalConsultation::factory()->signed()->create()->id;
            },
            'status' => 'active',
            'objective' => 'Control metabólico y adherencia terapéutica ambulatoria.',
            'created_at' => now(),
        ];
    }
}
