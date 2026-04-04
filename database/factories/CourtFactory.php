<?php

namespace Database\Factories;

use App\Models\Court;
use App\Models\Venue;
use App\Models\Sport;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class CourtFactory extends Factory
{
    protected $model = Court::class;

    public function definition(): array
    {
        $name = $this->faker->unique()->words(2, true);

        return [
            'venue_id' => Venue::factory(),
            'sport_id' => Sport::factory(),

            'name' => $name,

            'price_per_hour' => $this->faker->numberBetween(50000, 200000),

            'status' => 'active',

            'slug' => Str::slug($name),

            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    /**
     * Set inactive court
     */
    public function inactive(): static
    {
        return $this->state(fn() => [
            'status' => 'inactive',
        ]);
    }

    /**
     * Attach to specific venue
     */
    public function forVenue($venue): static
    {
        return $this->state(fn() => [
            'venue_id' => is_object($venue) ? $venue->id : $venue,
        ]);
    }

    /**
     * Attach to specific sport
     */
    public function forSport($sport): static
    {
        return $this->state(fn() => [
            'sport_id' => is_object($sport) ? $sport->id : $sport,
        ]);
    }
}
