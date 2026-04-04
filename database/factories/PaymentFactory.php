<?php

namespace Database\Factories;

use App\Models\Payment;
use App\Models\PaymentStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
  /**
   * Define the model's default state.
   *
   * @return array<string, mixed>
   */
  public function definition(): array
  {
    return [
      'booking_id' => null, // Will be set when creating
      'payment_method' => $this->faker->randomElement(['bank_transfer', 'credit_card', 'e_wallet']),
      'amount' => $this->faker->numberBetween(50000, 500000),
      'transaction_id' => $this->faker->uuid(),
      'payment_status_id' => PaymentStatus::pending(),
      'expired_at' => now()->addMinutes(10),
    ];
  }

  /**
   * Indicate that the payment is paid.
   */
  public function paid(): static
  {
    return $this->state(fn(array $attributes) => [
      'payment_status_id' => PaymentStatus::paid(),
      'paid_at' => now(),
    ]);
  }

  /**
   * Indicate that the payment is cancelled.
   */
  public function cancelled(): static
  {
    return $this->state(fn(array $attributes) => [
      'payment_status_id' => PaymentStatus::cancelled(),
    ]);
  }

  /**
   * Indicate that the payment is expired.
   */
  public function expired(): static
  {
    return $this->state(fn(array $attributes) => [
      'payment_status_id' => PaymentStatus::pending(),
      'expired_at' => now()->subMinutes(1),
    ]);
  }
}
