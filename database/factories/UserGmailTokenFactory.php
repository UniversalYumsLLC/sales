<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\UserGmailToken;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UserGmailToken>
 */
class UserGmailTokenFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'gmail_email' => fake()->safeEmail(),
            'access_token' => fake()->sha256(),
            'refresh_token' => fake()->sha256(),
            'token_expires_at' => now()->addHour(),
        ];
    }

    /**
     * Create an expired token.
     */
    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'token_expires_at' => now()->subHour(),
        ]);
    }
}
