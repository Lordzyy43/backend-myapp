<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * Cache password biar tidak hash berulang
     */
    protected static ?string $password;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),

            /**
             * 🔥 FIX: gunakan constant (deterministic)
             */
            'role_id' => User::ROLE_USER,
        ];
    }

    /**
     * =========================
     * STATE: ADMIN
     * =========================
     */
    public function admin(): static
    {
        return $this->state(fn() => [
            'role_id' => User::ROLE_ADMIN,
        ]);
    }

    /**
     * =========================
     * STATE: OWNER
     * =========================
     */
    public function owner(): static
    {
        return $this->state(fn() => [
            'role_id' => User::ROLE_OWNER,
        ]);
    }

    /**
     * =========================
     * STATE: UNVERIFIED
     * =========================
     */
    public function unverified(): static
    {
        return $this->state(fn() => [
            'email_verified_at' => null,
        ]);
    }
}
