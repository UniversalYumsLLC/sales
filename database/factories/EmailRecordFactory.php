<?php

namespace Database\Factories;

use App\Models\EmailRecord;
use App\Models\Invoice;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EmailRecord>
 */
class EmailRecordFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'fulfil_party_id' => fake()->randomNumber(5),
            'invoice_id' => Invoice::factory(),
            'email_type' => EmailRecord::TYPE_INITIAL_INVOICE,
            'sent_at' => now(),
            'pdf_path' => 'invoices/INV-'.fake()->randomNumber(6).'.pdf',
        ];
    }
}
