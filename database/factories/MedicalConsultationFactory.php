<?php

namespace Database\Factories;

use App\Models\Appointment;
use App\Models\Doctor;
use App\Models\MedicalConsultation;
use App\Models\Patient;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MedicalConsultation>
 */
class MedicalConsultationFactory extends Factory
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
            'doctor_id' => Doctor::factory(),
            'appointment_id' => null,
            'occurred_at' => now()->subMinutes(10),
            'status' => 'draft',
            'chief_complaint' => fake()->sentence(),
            'subjective' => 'Paciente refiere evolución general estable.',
            'objective' => 'Signos vitales dentro de parámetros normales.',
            'assessment' => 'Cuadro clínico en observación.',
            'plan' => 'Control ambulatorio y seguimiento.',
            'signed_at' => null,
        ];
    }

    public function signed(): static
    {
        return $this->state(fn (): array => [
            'status' => 'signed',
            'signed_at' => now(),
        ]);
    }

    public function forAppointment(Appointment $appointment): static
    {
        return $this->state(fn (): array => [
            'appointment_id' => $appointment->id,
            'patient_id' => $appointment->patient_id,
            'doctor_id' => $appointment->doctor_id,
        ]);
    }
}
