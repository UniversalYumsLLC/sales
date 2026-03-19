<?php

namespace Database\Factories;

use App\Models\LocalCustomerMetadata;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LocalCustomerMetadata>
 */
class LocalCustomerMetadataFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'fulfil_party_id' => fake()->unique()->randomNumber(5),
            'company_urls' => [fake()->domainName()],
            'customer_type' => LocalCustomerMetadata::CUSTOMER_TYPE_RETAILER,
            'broker' => false,
        ];
    }

    /**
     * Create a distributor customer.
     */
    public function distributor(): static
    {
        return $this->state(fn (array $attributes) => [
            'customer_type' => LocalCustomerMetadata::CUSTOMER_TYPE_DISTRIBUTOR,
        ]);
    }
}
