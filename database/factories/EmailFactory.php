<?php

namespace Database\Factories;

use App\Models\Email;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Email>
 */
class EmailFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'gmail_message_id' => fake()->unique()->uuid(),
            'gmail_thread_id' => fake()->uuid(),
            'direction' => Email::DIRECTION_INBOUND,
            'from_email' => fake()->safeEmail(),
            'from_name' => fake()->name(),
            'to_emails' => [fake()->safeEmail()],
            'cc_emails' => [],
            'subject' => fake()->sentence(),
            'body_text' => fake()->paragraph(),
            'body_html' => '<p>'.fake()->paragraph().'</p>',
            'email_date' => now()->subDays(fake()->numberBetween(1, 30)),
            'has_attachments' => false,
        ];
    }

    /**
     * Create an outbound email.
     */
    public function outbound(): static
    {
        return $this->state(fn (array $attributes) => [
            'direction' => Email::DIRECTION_OUTBOUND,
        ]);
    }
}
