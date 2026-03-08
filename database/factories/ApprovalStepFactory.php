<?php

namespace Database\Factories;

use App\Enums\ApprovalAssigneeType;
use App\Enums\ApprovalStepStatus;
use App\Enums\UserRole;
use App\Models\ApprovalWorkflow;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ApprovalStep>
 */
class ApprovalStepFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'workflow_id' => ApprovalWorkflow::factory(),
            'step_order' => fake()->numberBetween(1, 5),
            'name' => fake()->randomElement(['Review', 'Legal', 'Admin']),
            'assignee_type' => ApprovalAssigneeType::Role->value,
            'assignee_role' => fake()->randomElement(array_column(UserRole::cases(), 'value')),
            'status' => ApprovalStepStatus::Pending->value,
            'activated_at' => null,
            'decided_at' => null,
            'decision_note' => null,
            'due_at' => null,
        ];
    }
}
