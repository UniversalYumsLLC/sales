<?php

namespace Database\Factories;

use App\Models\Prospect;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Prospect>
 */
class ProspectFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_name' => fake()->company(),
            'status' => Prospect::STATUS_TARGET,
            'created_by' => User::factory(),
            'notes' => fake()->optional()->sentence(),
            'company_urls' => [fake()->domainName()],
            'broker' => false,
        ];
    }

    /**
     * Create a prospect with contacted status.
     */
    public function contacted(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Prospect::STATUS_CONTACTED,
        ]);
    }

    /**
     * Create a prospect with engaged status.
     */
    public function engaged(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Prospect::STATUS_ENGAGED,
        ]);
    }

    /**
     * Create a prospect with dormant status.
     */
    public function dormant(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Prospect::STATUS_DORMANT,
        ]);
    }
}
