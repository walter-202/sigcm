<?php

namespace Database\Factories;

use App\Models\AuditEvent;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AuditEvent>
 */
class AuditEventFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'actor_user_id' => User::factory(),
            'entity_name' => 'medical_consultations',
            'entity_id' => 1,
            'operation' => 'INSERT',
            'changed_fields' => [
                'source' => 'factory',
            ],
            'occurred_at' => now(),
        ];
    }
}
