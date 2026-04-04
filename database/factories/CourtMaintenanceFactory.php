<?php

namespace Database\Factories;

use App\Models\Court;
use Illuminate\Database\Eloquent\Factories\Factory;

class CourtMaintenanceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'court_id' => Court::factory(),
            'name' => 'Renovasi Lantai',
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDays(3)->toDateString(),
        ];
    }
}
