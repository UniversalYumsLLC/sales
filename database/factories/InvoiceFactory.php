<?php

namespace Database\Factories;

use App\Models\Invoice;
use App\Models\LocalCustomerMetadata;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Invoice>
 */
class InvoiceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'fulfil_id' => fake()->unique()->randomNumber(6),
            'number' => 'INV-'.fake()->unique()->randomNumber(6),
            'fulfil_party_id' => LocalCustomerMetadata::factory(),
            'due_date' => now()->addDays(30),
            'created_date' => now(),
            'last_modified_date' => now(),
            'total_amount' => fake()->randomFloat(2, 100, 10000),
            'balance' => fake()->randomFloat(2, 0, 10000),
            'state' => Invoice::STATE_POSTED,
        ];
    }

    /**
     * Create an overdue invoice.
     */
    public function overdue(): static
    {
        return $this->state(fn (array $attributes) => [
            'due_date' => now()->subDays(10),
            'state' => Invoice::STATE_POSTED,
        ]);
    }

    /**
     * Create a paid invoice.
     */
    public function paid(): static
    {
        return $this->state(fn (array $attributes) => [
            'state' => Invoice::STATE_PAID,
            'balance' => 0,
        ]);
    }
}
