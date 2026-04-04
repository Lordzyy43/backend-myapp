<?php

namespace Database\Factories;

use App\Models\Promo;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Promo>
 */
class PromoFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'promo_code' => strtoupper(fake()->unique()->word()),
            'description' => fake()->sentence(),
            'discount_type' => fake()->randomElement(['percentage', 'fixed']),
            'discount_value' => fake()->numberBetween(5, 50),
            'start_date' => now()->subDays(fake()->numberBetween(1, 30)),
            'end_date' => now()->addDays(fake()->numberBetween(7, 90)),
            'usage_limit' => fake()->numberBetween(10, 100),
            'used_count' => 0,
            'is_active' => true,
        ];
    }
}
