<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\UserInvite;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UserInvite>
 */
class UserInviteFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'email' => fake()->unique()->safeEmail(),
            'role' => User::ROLE_SALESPERSON,
            'invited_by' => User::factory(),
        ];
    }
}
