<?php

namespace Database\Factories;

use App\Models\MedicalConsultation;
use App\Models\Referral;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Referral>
 */
class ReferralFactory extends Factory
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
            'specialty_or_service' => 'Cardiología clínica',
            'reason' => 'Valoración especializada para ajuste terapéutico.',
            'status' => 'requested',
            'requested_at' => now(),
        ];
    }
}
