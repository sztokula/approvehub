<?php

namespace Database\Factories;

use App\Enums\ApprovalDecisionStatus;
use App\Models\ApprovalStep;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ApprovalDecision>
 */
class ApprovalDecisionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'step_id' => ApprovalStep::factory(),
            'actor_id' => User::factory(),
            'decision' => fake()->randomElement(array_column(ApprovalDecisionStatus::cases(), 'value')),
            'note' => fake()->optional()->sentence(),
        ];
    }
}
