<?php

namespace Database\Factories;

use App\Models\Sport;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class SportFactory extends Factory
{
    protected $model = Sport::class;

    public function definition(): array
    {
        $name = $this->faker->unique()->word();

        return [
            'name' => ucfirst($name),

            // slug optional → model akan auto-generate kalau null
            'slug' => null,

            'icon' => Str::slug($name) . '.png',

            'image' => 'sports/' . Str::slug($name) . '.jpg',

            'is_active' => true,

            'sort_order' => $this->faker->numberBetween(1, 100),
        ];
    }

    /**
     * State: inactive sport
     */
    public function inactive(): static
    {
        return $this->state(fn() => [
            'is_active' => false,
        ]);
    }

    /**
     * State: custom name (biar fleksibel di test)
     */
    public function withName(string $name): static
    {
        return $this->state(fn() => [
            'name' => $name,
            'slug' => null, // biar auto-generate ulang
        ]);
    }
}
