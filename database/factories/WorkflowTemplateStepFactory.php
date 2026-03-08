<?php

namespace Database\Factories;

use App\Enums\ApprovalAssigneeType;
use App\Enums\UserRole;
use App\Models\User;
use App\Models\WorkflowTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\WorkflowTemplateStep>
 */
class WorkflowTemplateStepFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $assigneeType = fake()->randomElement(array_column(ApprovalAssigneeType::cases(), 'value'));

        return [
            'workflow_template_id' => WorkflowTemplate::factory(),
            'step_order' => fake()->numberBetween(1, 5),
            'name' => fake()->words(2, true),
            'assignee_type' => $assigneeType,
            'assignee_role' => $assigneeType === ApprovalAssigneeType::Role->value
                ? fake()->randomElement(array_column(UserRole::cases(), 'value'))
                : null,
            'assignee_user_id' => $assigneeType === ApprovalAssigneeType::User->value ? User::factory() : null,
            'fallback_user_id' => User::factory(),
            'due_in_hours' => fake()->randomElement([null, 24, 48, 72]),
        ];
    }
}
