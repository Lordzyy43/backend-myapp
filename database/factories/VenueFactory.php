<?php

namespace Database\Factories;

use App\Models\Venue;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Venue>
 */
class VenueFactory extends Factory
{
  /**
   * Define the model's default state.
   *
   * @return array<string, mixed>
   */
  public function definition(): array
  {
    return [
      'name' => fake()->company(),
      'slug' => fake()->slug(),
      'address' => fake()->address(),
      'city' => fake()->city(),
      'description' => fake()->sentence(),
      'owner_id' => \App\Models\User::factory()->create(['role_id' => 3])->id, // owner role
    ];
  }
}
