<?php

namespace Database\Factories;

use App\Models\ClinicalOrder;
use App\Models\DiagnosticReport;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DiagnosticReport>
 */
class DiagnosticReportFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'clinical_order_id' => ClinicalOrder::factory(),
            'reviewed_by_user_id' => null,
            'status' => 'received',
            'interpretation' => 'Valores recibidos para revisión clínica.',
            'issued_at' => now()->subMinutes(15),
            'reviewed_at' => null,
            'created_at' => now(),
        ];
    }

    public function reviewed(?User $reviewer = null): static
    {
        return $this->state(fn (): array => [
            'status' => 'reviewed',
            'reviewed_by_user_id' => $reviewer?->id ?? User::factory(),
            'reviewed_at' => now(),
        ]);
    }
}
