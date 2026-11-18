<?php

namespace Database\Factories;

use App\Models\DiagnosticReport;
use App\Models\DiagnosticResultItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DiagnosticResultItem>
 */
class DiagnosticResultItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'diagnostic_report_id' => DiagnosticReport::factory(),
            'test_code' => 'GLU',
            'test_name' => 'Glucosa sérica en ayunas',
            'result_number' => 94.500,
            'result_text' => null,
            'unit_code' => 'mg/dL',
            'reference_range' => '70 - 99 mg/dL',
            'created_at' => now(),
        ];
    }
}
