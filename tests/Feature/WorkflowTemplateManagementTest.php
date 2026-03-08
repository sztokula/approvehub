<?php

use App\Enums\UserRole;
use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use App\Models\WorkflowTemplate;

function attachWorkflowTemplateRole(User $user, Organization $organization, UserRole $role): void
{
    $roleModel = Role::query()->firstOrCreate(['name' => $role]);

    $user->organizations()->attach($organization->id, [
        'role_id' => $roleModel->id,
        'joined_at' => now(),
    ]);
}

it('allows organization admin to manage workflow templates', function (): void {
    $organization = Organization::factory()->create();
    $admin = User::factory()->create(['email_verified_at' => now()]);
    $reviewer = User::factory()->create(['email_verified_at' => now()]);

    attachWorkflowTemplateRole($admin, $organization, UserRole::Admin);
    attachWorkflowTemplateRole($reviewer, $organization, UserRole::Reviewer);

    $this->actingAs($admin)
        ->get(route('organizations.workflow-templates.index', $organization))
        ->assertOk();

    $createPayload = [
        'name' => 'Contract Flow',
        'document_type' => 'contract',
        'is_default' => '1',
        'steps' => [
            [
                'step_order' => 1,
                'name' => 'Reviewer',
                'assignee_type' => 'role',
                'assignee_role' => UserRole::Reviewer->value,
                'assignee_user_id' => '',
                'fallback_user_id' => $reviewer->id,
                'due_in_hours' => 24,
            ],
            [
                'step_order' => 2,
                'name' => 'Admin',
                'assignee_type' => 'role',
                'assignee_role' => UserRole::Admin->value,
                'assignee_user_id' => '',
                'fallback_user_id' => '',
                'due_in_hours' => 24,
            ],
        ],
    ];

    $this->actingAs($admin)
        ->post(route('organizations.workflow-templates.store', $organization), $createPayload)
        ->assertRedirect(route('organizations.workflow-templates.index', $organization));

    $template = WorkflowTemplate::query()
        ->where('organization_id', $organization->id)
        ->where('name', 'Contract Flow')
        ->firstOrFail();

    expect($template->steps()->count())->toBe(2)
        ->and($template->is_default)->toBeTrue();

    $updatePayload = [
        'name' => 'Contract Flow v2',
        'document_type' => 'contract',
        'is_default' => '0',
        'steps' => [
            [
                'step_order' => 1,
                'name' => 'Assigned Reviewer',
                'assignee_type' => 'user',
                'assignee_role' => '',
                'assignee_user_id' => $reviewer->id,
                'fallback_user_id' => '',
                'due_in_hours' => 12,
            ],
        ],
    ];

    $this->actingAs($admin)
        ->put(route('organizations.workflow-templates.update', [$organization, $template]), $updatePayload)
        ->assertRedirect(route('organizations.workflow-templates.index', $organization));

    $template->refresh();

    expect($template->name)->toBe('Contract Flow v2')
        ->and($template->is_default)->toBeFalse()
        ->and($template->steps()->count())->toBe(1)
        ->and($template->steps()->firstOrFail()->assignee_user_id)->toBe($reviewer->id);

    $this->actingAs($admin)
        ->delete(route('organizations.workflow-templates.destroy', [$organization, $template]))
        ->assertRedirect(route('organizations.workflow-templates.index', $organization));

    $this->assertDatabaseMissing('workflow_templates', [
        'id' => $template->id,
    ]);
});

it('forbids non admin from managing workflow templates', function (): void {
    $organization = Organization::factory()->create();
    $viewer = User::factory()->create(['email_verified_at' => now()]);
    attachWorkflowTemplateRole($viewer, $organization, UserRole::Viewer);

    $this->actingAs($viewer)
        ->get(route('organizations.workflow-templates.index', $organization))
        ->assertForbidden();
});

it('validates assignee user belongs to organization membership', function (): void {
    $organization = Organization::factory()->create();
    $admin = User::factory()->create(['email_verified_at' => now()]);
    $externalUser = User::factory()->create(['email_verified_at' => now()]);
    attachWorkflowTemplateRole($admin, $organization, UserRole::Admin);

    $this->actingAs($admin)
        ->post(route('organizations.workflow-templates.store', $organization), [
            'name' => 'Invalid user template',
            'document_type' => 'general',
            'steps' => [
                [
                    'step_order' => 1,
                    'name' => 'External User',
                    'assignee_type' => 'user',
                    'assignee_role' => '',
                    'assignee_user_id' => $externalUser->id,
                    'fallback_user_id' => '',
                    'due_in_hours' => 10,
                ],
            ],
        ])
        ->assertSessionHasErrors(['steps.0.assignee_user_id']);
});
