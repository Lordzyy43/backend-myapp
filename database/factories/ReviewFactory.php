<?php

namespace Database\Factories;

use App\Models\Review;
use App\Models\User;
use App\Models\Court;
use App\Models\Booking;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Review>
 */
class ReviewFactory extends Factory
{
  /**
   * The name of the factory's corresponding model.
   *
   * @var string
   */
  protected $model = Review::class;

  /**
   * Define the model's default state.
   *
   * @return array<string, mixed>
   */
  public function definition(): array
  {
    return [
      'booking_id' => Booking::factory(),
      'court_id' => Court::factory(),
      'user_id' => User::factory(),
      'rating' => fake()->numberBetween(1, 5),
      'comment' => fake()->text(200),
      'helpful_count' => 0,
      'is_verified_booking' => true,
      'is_flagged' => false,
      'flag_reason' => null,
    ];
  }

  /**
   * State: 5-star rating
   */
  public function fiveStar(): static
  {
    return $this->state(fn(array $attributes) => [
      'rating' => 5,
    ]);
  }

  /**
   * State: 4-star rating
   */
  public function fourStar(): static
  {
    return $this->state(fn(array $attributes) => [
      'rating' => 4,
    ]);
  }

  /**
   * State: 3-star rating
   */
  public function threeStar(): static
  {
    return $this->state(fn(array $attributes) => [
      'rating' => 3,
    ]);
  }

  /**
   * State: 2-star rating
   */
  public function twoStar(): static
  {
    return $this->state(fn(array $attributes) => [
      'rating' => 2,
    ]);
  }

  /**
   * State: 1-star rating
   */
  public function oneStar(): static
  {
    return $this->state(fn(array $attributes) => [
      'rating' => 1,
    ]);
  }

  /**
   * State: Flagged review
   */
  public function flagged(): static
  {
    return $this->state(fn(array $attributes) => [
      'is_flagged' => true,
      'flag_reason' => fake()->randomElement(['spam', 'offensive', 'inappropriate', 'irrelevant']),
    ]);
  }

  /**
   * State: Popular review (many helpful votes)
   */
  public function popular(): static
  {
    return $this->state(fn(array $attributes) => [
      'helpful_count' => fake()->numberBetween(10, 100),
    ]);
  }
}
