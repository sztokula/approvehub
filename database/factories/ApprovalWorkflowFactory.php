<?php

namespace Database\Factories;

use App\Enums\WorkflowStatus;
use App\Models\DocumentVersion;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ApprovalWorkflow>
 */
class ApprovalWorkflowFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'document_version_id' => DocumentVersion::factory(),
            'status' => WorkflowStatus::Pending->value,
            'submitted_by' => User::factory(),
            'submitted_at' => now(),
            'completed_at' => null,
        ];
    }
}
