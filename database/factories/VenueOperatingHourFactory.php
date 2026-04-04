<?php

namespace Database\Factories;

use App\Models\Venue;
use Illuminate\Database\Eloquent\Factories\Factory;

class VenueOperatingHourFactory extends Factory
{
    public function definition(): array
    {
        return [
            'venue_id' => Venue::factory(), // Otomatis buat venue kalau belum ada
            'day_of_week' => fake()->numberBetween(0, 6), // 0 = Minggu, 6 = Sabtu
            'open_time' => '08:00:00',
            'close_time' => '22:00:00',
            'is_closed' => false,
        ];
    }
}
