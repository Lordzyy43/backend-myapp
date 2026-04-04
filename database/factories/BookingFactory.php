<?php

namespace Database\Factories;

use App\Models\Booking;
use App\Models\BookingStatus;
use App\Models\User;
use App\Models\Court;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Booking>
 */
class BookingFactory extends Factory
{
  public function definition(): array
  {
    return [
      'user_id' => User::factory(),
      'court_id' => Court::factory(),

      'booking_date' => $this->faker
        ->dateTimeBetween('tomorrow', '+30 days')
        ->format('Y-m-d'),

      'status_id' => $this->resolveStatus(BookingStatus::pending()),

      'total_price' => $this->faker->numberBetween(50000, 500000),

      'expires_at' => now()->addMinutes(10),
    ];
  }

  /**
   * Handle flexible status (ID / Model safe)
   */
  protected function resolveStatus($status)
  {
    return is_object($status) ? $status->id : $status;
  }

  /**
   * CONFIRMED
   */
  public function confirmed(): static
  {
    return $this->state(fn() => [
      'status_id' => $this->resolveStatus(BookingStatus::confirmed()),
    ]);
  }

  /**
   * CANCELLED
   */
  public function cancelled(): static
  {
    return $this->state(fn() => [
      'status_id' => $this->resolveStatus(BookingStatus::cancelled()),
    ]);
  }

  /**
   * EXPIRED
   */
  public function expired(): static
  {
    return $this->state(fn() => [
      'status_id' => $this->resolveStatus(BookingStatus::expired()),
      'expires_at' => now()->subMinutes(1),
    ]);
  }

  /**
   * Attach specific user
   */
  public function forUser($user): static
  {
    return $this->state(fn() => [
      'user_id' => is_object($user) ? $user->id : $user,
    ]);
  }

  /**
   * Attach specific court
   */
  public function forCourt($court): static
  {
    return $this->state(fn() => [
      'court_id' => is_object($court) ? $court->id : $court,
    ]);
  }
}
