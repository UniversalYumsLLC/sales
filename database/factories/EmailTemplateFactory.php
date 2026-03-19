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
        $key = fake()->unique()->randomElement(EmailTemplate::getValidKeys());

        return [
            'key' => $key,
            'name' => ucwords(str_replace('_', ' ', $key)),
            'subject' => 'Invoice {{invoice_number}} from Universal Yums',
            'body' => 'Dear {{customer_name}}, please find attached invoice {{invoice_number}}.',
        ];
    }
}
