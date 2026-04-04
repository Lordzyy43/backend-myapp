<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class TimeSlotFactory extends Factory
{
  public function definition(): array
  {
    // Membuat jam start acak antara 07:00 - 21:00
    $startHour = $this->faker->unique()->numberBetween(7, 21);
    $startTime = sprintf('%02d:00:00', $startHour);
    $endTime = sprintf('%02d:00:00', $startHour + 1);

    return [
      'start_time' => $startTime,
      'end_time' => $endTime,
      'order_index' => $startHour,
      'is_active' => true,
      'label' => substr($startTime, 0, 5) . ' - ' . substr($endTime, 0, 5),
    ];
  }
}
