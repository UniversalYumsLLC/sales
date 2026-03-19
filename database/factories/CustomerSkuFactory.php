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
            // LocalCustomerMetadata uses non-standard PK (fulfil_party_id)
            'fulfil_party_id' => fn () => LocalCustomerMetadata::factory()->create()->fulfil_party_id,
            'yums_sku' => 'YUMS-'.fake()->unique()->randomNumber(4),
            'customer_sku' => 'CUST-'.fake()->unique()->randomNumber(4),
        ];
    }
}
