<?php

namespace Database\Factories;

use App\Models\EmailTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EmailTemplate>
 */
class EmailTemplateFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'key' => EmailTemplate::TYPE_INITIAL_INVOICE,
            'name' => 'Initial Invoice',
            'subject' => 'Invoice {{invoice_number}} from Universal Yums',
            'body' => 'Dear {{customer_name}}, please find attached invoice {{invoice_number}}.',
        ];
    }
}
