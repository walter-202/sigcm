<?php

namespace Database\Factories;

use App\Models\CareEpisode;
use App\Models\ClinicalRecord;
use App\Models\MedicalConsultation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CareEpisode>
 */
class CareEpisodeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'clinical_record_id' => ClinicalRecord::factory(),
            'opening_consultation_id' => function (array $attributes): int {
                $record = ClinicalRecord::query()->find($attributes['clinical_record_id']);

                if ($record !== null) {
                    return (int) $record->opened_from_consultation_id;
                }

                return MedicalConsultation::factory()->signed()->create()->id;
            },
            'title' => 'Episodio clínico ambulatorio',
            'status' => 'active',
            'opened_at' => now(),
            'closed_at' => null,
        ];
    }
}
