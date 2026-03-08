<?php

namespace App\Actions\Workflows;

use App\Enums\ApprovalAssigneeType;
use App\Enums\UserRole;
use App\Models\Organization;
use App\Models\WorkflowTemplate;

/**
 * Handles EnsureOrganizationWorkflowTemplatesAction responsibilities for the ApproveHub domain.
 */
class EnsureOrganizationWorkflowTemplatesAction
{
    public function execute(Organization $organization): void
    {
        $definitions = [
            [
                'name' => 'General 2-step Review',
                'document_type' => 'general',
                'is_default' => true,
                'steps' => [
                    ['step_order' => 1, 'name' => 'Reviewer', 'assignee_type' => ApprovalAssigneeType::Role->value, 'assignee_role' => UserRole::Reviewer->value, 'due_in_hours' => 24],
                    ['step_order' => 2, 'name' => 'Admin', 'assignee_type' => ApprovalAssigneeType::Role->value, 'assignee_role' => UserRole::Admin->value, 'due_in_hours' => 24],
                ],
            ],
            [
                'name' => 'Contract Legal Flow',
                'document_type' => 'contract',
                'is_default' => true,
                'steps' => [
                    ['step_order' => 1, 'name' => 'Legal Review', 'assignee_type' => ApprovalAssigneeType::Role->value, 'assignee_role' => UserRole::Reviewer->value, 'due_in_hours' => 48],
                    ['step_order' => 2, 'name' => 'Admin Sign-off', 'assignee_type' => ApprovalAssigneeType::Role->value, 'assignee_role' => UserRole::Admin->value, 'due_in_hours' => 24],
                ],
            ],
            [
                'name' => 'Policy Publication Flow',
                'document_type' => 'policy',
                'is_default' => true,
                'steps' => [
                    ['step_order' => 1, 'name' => 'Editorial Review', 'assignee_type' => ApprovalAssigneeType::Role->value, 'assignee_role' => UserRole::Editor->value, 'due_in_hours' => 24],
                    ['step_order' => 2, 'name' => 'Final Admin Approval', 'assignee_type' => ApprovalAssigneeType::Role->value, 'assignee_role' => UserRole::Admin->value, 'due_in_hours' => 24],
                ],
            ],
        ];

        foreach ($definitions as $definition) {
            $template = WorkflowTemplate::query()->firstOrCreate(
                [
                    'organization_id' => $organization->id,
                    'name' => $definition['name'],
                ],
                [
                    'document_type' => $definition['document_type'],
                    'is_default' => $definition['is_default'],
                ],
            );

            foreach ($definition['steps'] as $stepDefinition) {
                $template->steps()->firstOrCreate(
                    ['step_order' => $stepDefinition['step_order']],
                    $stepDefinition,
                );
            }
        }
    }
}
