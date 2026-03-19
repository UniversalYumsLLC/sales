<?php

namespace Database\Factories;

use App\Models\CustomerSku;
use App\Models\LocalCustomerMetadata;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CustomerSku>
 */
class CustomerSkuFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'fulfil_party_id' => LocalCustomerMetadata::factory(),
            'yums_sku' => 'YUMS-'.fake()->unique()->randomNumber(4),
            'customer_sku' => 'CUST-'.fake()->unique()->randomNumber(4),
        ];
    }
}
