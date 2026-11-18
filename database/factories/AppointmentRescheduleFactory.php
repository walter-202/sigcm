<?php

namespace Database\Factories;

use App\Models\Appointment;
use App\Models\AppointmentReschedule;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AppointmentReschedule>
 */
class AppointmentRescheduleFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $prevStart = now()->addDays(5)->setTime(9, 0);
        $prevEnd = (clone $prevStart)->addHour();
        $newStart = (clone $prevStart)->addDays(1);
        $newEnd = (clone $newStart)->addHour();

        return [
            'appointment_id' => Appointment::factory(),
            'changed_by_user_id' => User::factory(),
            'previous_start' => $prevStart,
            'previous_end' => $prevEnd,
            'new_start' => $newStart,
            'new_end' => $newEnd,
            'reason' => 'Reprogramación solicitada por agenda',
            'created_at' => now(),
        ];
    }
}
