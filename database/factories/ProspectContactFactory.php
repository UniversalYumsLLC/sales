<?php

namespace Database\Factories;

use App\Models\Prospect;
use App\Models\ProspectContact;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProspectContact>
 */
class ProspectContactFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'prospect_id' => Prospect::factory(),
            'type' => ProspectContact::TYPE_BUYER,
            'name' => fake()->name(),
            'value' => fake()->safeEmail(),
        ];
    }

    /**
     * Create a buyer contact.
     */
    public function buyer(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => ProspectContact::TYPE_BUYER,
        ]);
    }

    /**
     * Create an accounts payable contact.
     */
    public function accountsPayable(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => ProspectContact::TYPE_ACCOUNTS_PAYABLE,
        ]);
    }

    /**
     * Create an uncategorized contact.
     */
    public function uncategorized(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => ProspectContact::TYPE_UNCATEGORIZED,
        ]);
    }

    /**
     * Create a broker contact.
     */
    public function broker(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => ProspectContact::TYPE_BROKER,
        ]);
    }
}
