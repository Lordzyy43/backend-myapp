<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class BookingStatusFactory extends Factory
{
  public function definition(): array
  {
    return [
      'status_name' => $this->faker->unique()->word(),
    ];
  }

  // 🔥 State untuk status Confirmed
  public function confirmed()
  {
    return $this->state(fn(array $attributes) => [
      'status_name' => 'Confirmed', // Sesuaikan dengan yang dicek di logic kamu
    ]);
  }

  // 🔥 State untuk status Pending
  public function pending()
  {
    return $this->state(fn(array $attributes) => [
      'status_name' => 'Pending',
    ]);
  }
}
