<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\WebhookDelivery>
 */
class WebhookDeliveryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'event' => fake()->randomElement(['review.submitted', 'approval.rejected']),
            'organization_id' => fake()->numberBetween(1, 5000),
            'actor_id' => fake()->optional()->numberBetween(1, 5000),
            'target_type' => fake()->randomElement(['document', 'approval_step', 'approval_workflow']),
            'target_id' => fake()->numberBetween(1, 5000),
            'webhook_url' => fake()->url(),
            'response_status' => fake()->randomElement([200, 202, 400, 500]),
            'response_body' => fake()->text(120),
            'succeeded' => fake()->boolean(),
            'error_message' => fake()->optional()->sentence(),
            'attempted_at' => now(),
        ];
    }
}
