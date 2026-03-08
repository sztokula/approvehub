<?php

namespace Database\Factories;

use App\Models\ApprovalStep;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ApprovalStepAssignee>
 */
class ApprovalStepAssigneeFactory extends Factory
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
            'user_id' => User::factory(),
            'is_required' => true,
        ];
    }
}
