<?php

use App\Actions\Approvals\ApproveStepAction;
use App\Actions\Documents\CreateDocumentAction;
use App\Enums\UserRole;
use App\Models\ApprovalStep;
use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use App\Models\WorkflowTemplate;
use App\Models\WorkflowTemplateStep;

function attachTemplateMembership(User $user, Organization $organization, UserRole $role): void
{
    $roleModel = Role::query()->firstOrCreate(['name' => $role]);

    $user->organizations()->attach($organization->id, [
        'role_id' => $roleModel->id,
        'joined_at' => now(),
    ]);
}

it('submits document for review using workflow template and fallback assignee', function (): void {
    $owner = User::factory()->create(['email_verified_at' => now()]);
    $fallbackReviewer = User::factory()->create(['email_verified_at' => now()]);
    $admin = User::factory()->create(['email_verified_at' => now()]);
    $organization = Organization::factory()->create();

    attachTemplateMembership($owner, $organization, UserRole::Editor);
    attachTemplateMembership($fallbackReviewer, $organization, UserRole::Viewer);
    attachTemplateMembership($admin, $organization, UserRole::Admin);

    $template = WorkflowTemplate::factory()->create([
        'organization_id' => $organization->id,
        'name' => 'Contract fallback flow',
        'document_type' => 'contract',
    ]);

    WorkflowTemplateStep::factory()->create([
        'workflow_template_id' => $template->id,
        'step_order' => 1,
        'name' => 'Reviewer',
        'assignee_type' => 'role',
        'assignee_role' => UserRole::Reviewer->value,
        'fallback_user_id' => $fallbackReviewer->id,
        'assignee_user_id' => null,
        'due_in_hours' => 24,
    ]);

    WorkflowTemplateStep::factory()->create([
        'workflow_template_id' => $template->id,
        'step_order' => 2,
        'name' => 'Admin',
        'assignee_type' => 'role',
        'assignee_role' => UserRole::Admin->value,
        'fallback_user_id' => null,
        'assignee_user_id' => null,
        'due_in_hours' => 24,
    ]);

    $document = app(CreateDocumentAction::class)->execute(
        organization: $organization,
        owner: $owner,
        title: 'Master Contract',
        description: 'Contract document',
        content: 'v1',
        documentType: 'contract',
    );

    $this->actingAs($owner)
        ->post(route('documents.review.store', $document), [
            'template_id' => $template->id,
        ])
        ->assertRedirect(route('documents.show', $document));

    $activeStep = ApprovalStep::query()
        ->whereHas('workflow.documentVersion', fn ($query) => $query->where('id', $document->current_version_id))
        ->where('step_order', 1)
        ->firstOrFail();

    expect($activeStep->fallback_user_id)->toBe($fallbackReviewer->id)
        ->and($activeStep->due_at)->not()->toBeNull();

    app(ApproveStepAction::class)->execute($activeStep, $fallbackReviewer);

    $document->refresh();
    expect($document->status->value)->toBe('in_review');
});

it('rejects template submission when document type does not match template type', function (): void {
    $owner = User::factory()->create(['email_verified_at' => now()]);
    $organization = Organization::factory()->create();

    attachTemplateMembership($owner, $organization, UserRole::Editor);

    $template = WorkflowTemplate::factory()->create([
        'organization_id' => $organization->id,
        'document_type' => 'policy',
    ]);

    WorkflowTemplateStep::factory()->create([
        'workflow_template_id' => $template->id,
        'step_order' => 1,
        'assignee_type' => 'role',
        'assignee_role' => UserRole::Admin->value,
        'assignee_user_id' => null,
    ]);

    $document = app(CreateDocumentAction::class)->execute(
        organization: $organization,
        owner: $owner,
        title: 'NDA',
        description: 'Contract document',
        content: 'v1',
        documentType: 'contract',
    );

    $this->actingAs($owner)
        ->withHeader('Accept', 'application/json')
        ->post(route('documents.review.store', $document), [
            'template_id' => $template->id,
        ])
        ->assertStatus(422);
});

it('rejects manual reviewer assignment for user outside organization', function (): void {
    $owner = User::factory()->create(['email_verified_at' => now()]);
    $externalReviewer = User::factory()->create(['email_verified_at' => now()]);
    $organization = Organization::factory()->create();

    attachTemplateMembership($owner, $organization, UserRole::Editor);

    $document = app(CreateDocumentAction::class)->execute(
        organization: $organization,
        owner: $owner,
        title: 'Manual Review Scope',
        description: 'Contract document',
        content: 'v1',
        documentType: 'contract',
    );

    $this->actingAs($owner)
        ->withHeader('Accept', 'application/json')
        ->post(route('documents.review.store', $document), [
            'reviewers' => (string) $externalReviewer->id,
        ])
        ->assertInvalid(['steps.0.assignees.0']);
});
