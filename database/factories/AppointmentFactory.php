<?php

namespace Database\Factories;

use App\Models\Appointment;
use App\Models\Doctor;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Appointment>
 */
class AppointmentFactory extends Factory
{
    private static int $slotCounter = 0;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        self::$slotCounter++;
        $start = now()->addDays(30)->startOfDay()->addHours(8 + (self::$slotCounter * 2));
        $end = (clone $start)->addHour();

        return [
            'patient_id' => Patient::factory(),
            'doctor_id' => Doctor::factory(),
            'created_by_user_id' => User::factory(),
            'starts_at' => $start,
            'ends_at' => $end,
            'status' => 'confirmed',
            'reason' => 'Consulta médica programada',
            'cancellation_reason' => null,
        ];
    }
}
