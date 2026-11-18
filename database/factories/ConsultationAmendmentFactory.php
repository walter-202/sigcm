<?php

namespace Database\Factories;

use App\Models\ConsultationAmendment;
use App\Models\MedicalConsultation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ConsultationAmendment>
 */
class ConsultationAmendmentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'consultation_id' => MedicalConsultation::factory()->signed(),
            'author_user_id' => User::factory(),
            'reason' => 'Aclaración documental posterior a firma',
            'additional_note' => 'Anexo clínico complementario sin modificar la firma original.',
            'created_at' => now(),
        ];
    }
}
