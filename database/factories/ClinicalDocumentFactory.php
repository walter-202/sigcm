<?php

namespace Database\Factories;

use App\Models\ClinicalDocument;
use App\Models\MedicalConsultation;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ClinicalDocument>
 */
class ClinicalDocumentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'patient_id' => Patient::factory(),
            'consultation_id' => function (array $attributes): int {
                return MedicalConsultation::factory()->create([
                    'patient_id' => $attributes['patient_id'],
                ])->id;
            },
            'uploaded_by_user_id' => User::factory(),
            'document_type' => 'informe_externo',
            'storage_path' => 'documentos/demo-informe.pdf',
            'mime_type' => 'application/pdf',
            'uploaded_at' => now(),
            'created_at' => now(),
        ];
    }
}
