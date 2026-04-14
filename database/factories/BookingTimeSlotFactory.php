<?php

namespace Database\Factories;

use App\Models\Booking;
use App\Models\TimeSlot;
use App\Models\Court;
use Illuminate\Database\Eloquent\Factories\Factory;

class BookingTimeSlotFactory extends Factory
{
    public function definition(): array
    {
        return [
            'booking_id' => Booking::factory(),
            'time_slot_id' => TimeSlot::factory(),
            'court_id' => Court::factory(),
            'booking_date' => now()->toDateString(),
        ];
    }
}
