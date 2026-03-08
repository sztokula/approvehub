<?php

use App\Actions\Approvals\ApproveStepAction;
use App\Actions\Approvals\RejectStepAction;
use App\Actions\Documents\CreateDocumentAction;
use App\Actions\Documents\CreateDocumentVersionAction;
use App\Actions\Documents\RestoreDocumentVersionAction;
use App\Actions\Documents\SubmitDocumentForReviewAction;
use App\Enums\DocumentStatus;
use App\Enums\DocumentVisibility;
use App\Enums\UserRole;
use App\Enums\WorkflowStatus;
use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use App\Notifications\ReviewRejectedNotification;
use App\Notifications\ReviewStepActivatedNotification;
use App\Notifications\ReviewSubmittedNotification;
use Illuminate\Support\Facades\Notification;

it('creates document with immutable version and audit logs', function (): void {
    $owner = User::factory()->create();
    $organization = Organization::factory()->create();
    $role = Role::query()->create(['name' => UserRole::Editor]);

    $owner->organizations()->attach($organization->id, ['role_id' => $role->id, 'joined_at' => now()]);

    $document = app(CreateDocumentAction::class)->execute(
        organization: $organization,
        owner: $owner,
        title: 'Security Policy',
        description: 'Baseline controls',
        content: 'v1 content',
        visibility: DocumentVisibility::Organization,
    );

    expect($document->status)->toBe(DocumentStatus::Draft)
        ->and($document->currentVersion)->not()->toBeNull()
        ->and($document->versions()->count())->toBe(1)
        ->and($organization->auditLogs()->count())->toBe(2);
});

it('creates next version and updates current pointer', function (): void {
    $owner = User::factory()->create();
    $organization = Organization::factory()->create();
    $role = Role::query()->create(['name' => UserRole::Editor]);

    $owner->organizations()->attach($organization->id, ['role_id' => $role->id, 'joined_at' => now()]);

    $document = app(CreateDocumentAction::class)->execute(
        organization: $organization,
        owner: $owner,
        title: 'Contract',
        description: 'Draft',
        content: 'v1',
    );

    $newVersion = app(CreateDocumentVersionAction::class)->execute(
        document: $document->fresh(),
        actor: $owner,
        titleSnapshot: 'Contract v2',
        contentSnapshot: 'v2',
    );

    $document->refresh();

    expect($newVersion->version_number)->toBe(2)
        ->and($document->current_version_id)->toBe($newVersion->id)
        ->and($document->versions()->count())->toBe(2)
        ->and($organization->auditLogs()->count())->toBe(4);
});

it('submits current version for review and activates first step', function (): void {
    $owner = User::factory()->create();
    $reviewer = User::factory()->create();
    $organization = Organization::factory()->create();
    $editorRole = Role::query()->create(['name' => UserRole::Editor]);
    $reviewerRole = Role::query()->firstOrCreate(['name' => UserRole::Reviewer]);

    $owner->organizations()->attach($organization->id, ['role_id' => $editorRole->id, 'joined_at' => now()]);
    $reviewer->organizations()->attach($organization->id, ['role_id' => $reviewerRole->id, 'joined_at' => now()]);

    $document = app(CreateDocumentAction::class)->execute(
        organization: $organization,
        owner: $owner,
        title: 'Offer',
        description: 'Commercial',
        content: 'Offer text',
    );

    app(SubmitDocumentForReviewAction::class)->execute(
        version: $document->currentVersion,
        actor: $owner,
        steps: [
            [
                'name' => 'Reviewer',
                'assignee_type' => 'user',
                'assignees' => [$reviewer->id],
            ],
            [
                'name' => 'Admin',
                'assignee_type' => 'role',
                'assignee_role' => UserRole::Admin->value,
            ],
        ],
    );

    $document->refresh();
    $workflow = $document->currentVersion->workflow()->with('steps')->first();

    expect($document->status)->toBe(DocumentStatus::InReview)
        ->and($workflow)->not()->toBeNull()
        ->and($workflow->status)->toBe(WorkflowStatus::InProgress)
        ->and($workflow->steps->first()->status->value)->toBe('active')
        ->and($workflow->steps->last()->status->value)->toBe('pending');
});

it('approves all steps and marks document approved', function (): void {
    $owner = User::factory()->create();
    $reviewer = User::factory()->create();
    $organization = Organization::factory()->create();
    $editorRole = Role::query()->create(['name' => UserRole::Editor]);
    $reviewerRole = Role::query()->firstOrCreate(['name' => UserRole::Reviewer]);
    $adminRole = Role::query()->firstOrCreate(['name' => UserRole::Admin]);

    $owner->organizations()->attach($organization->id, ['role_id' => $editorRole->id, 'joined_at' => now()]);
    $reviewer->organizations()->attach($organization->id, ['role_id' => $reviewerRole->id, 'joined_at' => now()]);
    $admin = User::factory()->create();
    $admin->organizations()->attach($organization->id, ['role_id' => $adminRole->id, 'joined_at' => now()]);

    $document = app(CreateDocumentAction::class)->execute(
        organization: $organization,
        owner: $owner,
        title: 'Internal Policy',
        description: 'Policy',
        content: 'Content',
    );

    app(SubmitDocumentForReviewAction::class)->execute(
        version: $document->currentVersion,
        actor: $owner,
        steps: [
            ['name' => 'Reviewer', 'assignee_type' => 'user', 'assignees' => [$reviewer->id]],
            ['name' => 'Admin', 'assignee_type' => 'role', 'assignee_role' => UserRole::Admin->value],
        ],
    );

    $workflow = $document->currentVersion->workflow()->with('steps')->firstOrFail();
    $firstStep = $workflow->steps->first();
    $secondStep = $workflow->steps->last();

    app(ApproveStepAction::class)->execute($firstStep, $reviewer);
    app(ApproveStepAction::class)->execute($secondStep->fresh(), $admin);

    $document->refresh();
    $workflow->refresh();

    expect($document->status)->toBe(DocumentStatus::Approved)
        ->and($workflow->status)->toBe(WorkflowStatus::Approved);
});

it('rejects active step and marks document rejected', function (): void {
    $owner = User::factory()->create();
    $reviewer = User::factory()->create();
    $organization = Organization::factory()->create();
    $editorRole = Role::query()->create(['name' => UserRole::Editor]);
    $reviewerRole = Role::query()->firstOrCreate(['name' => UserRole::Reviewer]);

    $owner->organizations()->attach($organization->id, ['role_id' => $editorRole->id, 'joined_at' => now()]);
    $reviewer->organizations()->attach($organization->id, ['role_id' => $reviewerRole->id, 'joined_at' => now()]);

    $document = app(CreateDocumentAction::class)->execute(
        organization: $organization,
        owner: $owner,
        title: 'NDA',
        description: 'Agreement',
        content: 'NDA content',
    );

    app(SubmitDocumentForReviewAction::class)->execute(
        version: $document->currentVersion,
        actor: $owner,
        steps: [
            ['name' => 'Reviewer', 'assignee_type' => 'user', 'assignees' => [$reviewer->id]],
        ],
    );

    $step = $document->currentVersion->workflow->steps()->firstOrFail();
    app(RejectStepAction::class)->execute($step, $reviewer, 'Missing legal clause');

    $document->refresh();
    $workflow = $document->currentVersion->workflow()->firstOrFail();

    expect($document->status)->toBe(DocumentStatus::Rejected)
        ->and($workflow->status)->toBe(WorkflowStatus::Rejected);
});

it('restores old version as a new snapshot and records audit event', function (): void {
    $owner = User::factory()->create();
    $organization = Organization::factory()->create();
    $role = Role::query()->create(['name' => UserRole::Editor]);

    $owner->organizations()->attach($organization->id, ['role_id' => $role->id, 'joined_at' => now()]);

    $document = app(CreateDocumentAction::class)->execute(
        organization: $organization,
        owner: $owner,
        title: 'Playbook v1',
        description: 'Desc',
        content: 'Version one',
    );

    app(CreateDocumentVersionAction::class)->execute(
        document: $document,
        actor: $owner,
        titleSnapshot: 'Playbook v2',
        contentSnapshot: 'Version two',
    );

    $versionOne = $document->versions()->where('version_number', 1)->firstOrFail();

    $restored = app(RestoreDocumentVersionAction::class)->execute(
        document: $document->fresh(),
        versionToRestore: $versionOne,
        actor: $owner,
    );

    $document->refresh();

    expect($restored->version_number)->toBe(3)
        ->and($restored->content_snapshot)->toBe('Version one')
        ->and($document->current_version_id)->toBe($restored->id)
        ->and($organization->auditLogs()->where('action', 'version.restored')->count())->toBe(1);
});

it('prevents resubmitting the same version for review', function (): void {
    $owner = User::factory()->create();
    $reviewer = User::factory()->create();
    $organization = Organization::factory()->create();
    $editorRole = Role::query()->create(['name' => UserRole::Editor]);
    $reviewerRole = Role::query()->firstOrCreate(['name' => UserRole::Reviewer]);

    $owner->organizations()->attach($organization->id, ['role_id' => $editorRole->id, 'joined_at' => now()]);
    $reviewer->organizations()->attach($organization->id, ['role_id' => $reviewerRole->id, 'joined_at' => now()]);

    $document = app(CreateDocumentAction::class)->execute(
        organization: $organization,
        owner: $owner,
        title: 'Repeat submit',
        description: 'Desc',
        content: 'Content',
    );

    app(SubmitDocumentForReviewAction::class)->execute(
        version: $document->currentVersion,
        actor: $owner,
        steps: [[
            'name' => 'Reviewer',
            'assignee_type' => 'user',
            'assignees' => [$reviewer->id],
        ]],
    );

    expect(fn () => app(SubmitDocumentForReviewAction::class)->execute(
        version: $document->currentVersion->fresh(),
        actor: $owner,
        steps: [[
            'name' => 'Reviewer',
            'assignee_type' => 'user',
            'assignees' => [$reviewer->id],
        ]],
    ))->toThrow(\DomainException::class);
});

it('dispatches queued notifications for submit step activation and rejection', function (): void {
    Notification::fake();

    $owner = User::factory()->create();
    $reviewer = User::factory()->create();
    $admin = User::factory()->create();
    $organization = Organization::factory()->create();
    $editorRole = Role::query()->create(['name' => UserRole::Editor]);
    $reviewerRole = Role::query()->firstOrCreate(['name' => UserRole::Reviewer]);
    $adminRole = Role::query()->firstOrCreate(['name' => UserRole::Admin]);

    $owner->organizations()->attach($organization->id, ['role_id' => $editorRole->id, 'joined_at' => now()]);
    $reviewer->organizations()->attach($organization->id, ['role_id' => $reviewerRole->id, 'joined_at' => now()]);
    $admin->organizations()->attach($organization->id, ['role_id' => $adminRole->id, 'joined_at' => now()]);

    $document = app(CreateDocumentAction::class)->execute(
        organization: $organization,
        owner: $owner,
        title: 'Notify flow',
        description: 'Desc',
        content: 'Content',
    );

    app(SubmitDocumentForReviewAction::class)->execute(
        version: $document->currentVersion,
        actor: $owner,
        steps: [
            ['name' => 'Reviewer', 'assignee_type' => 'user', 'assignees' => [$reviewer->id]],
            ['name' => 'Admin', 'assignee_type' => 'role', 'assignee_role' => UserRole::Admin->value],
        ],
    );

    Notification::assertSentTo($reviewer, ReviewSubmittedNotification::class);

    $workflow = $document->currentVersion->workflow()->with('steps')->firstOrFail();
    $firstStep = $workflow->steps->first();
    $secondStep = $workflow->steps->last();

    app(ApproveStepAction::class)->execute($firstStep, $reviewer);
    Notification::assertSentTo($admin, ReviewStepActivatedNotification::class);

    app(RejectStepAction::class)->execute($secondStep->fresh(), $admin, 'Final rejection');
    Notification::assertSentTo($owner, ReviewRejectedNotification::class);
});

it('prevents double decision on the same step', function (): void {
    $owner = User::factory()->create();
    $reviewer = User::factory()->create();
    $organization = Organization::factory()->create();
    $editorRole = Role::query()->create(['name' => UserRole::Editor]);
    $reviewerRole = Role::query()->firstOrCreate(['name' => UserRole::Reviewer]);

    $owner->organizations()->attach($organization->id, ['role_id' => $editorRole->id, 'joined_at' => now()]);
    $reviewer->organizations()->attach($organization->id, ['role_id' => $reviewerRole->id, 'joined_at' => now()]);

    $document = app(CreateDocumentAction::class)->execute(
        organization: $organization,
        owner: $owner,
        title: 'Double decision',
        description: 'Desc',
        content: 'Content',
    );

    app(SubmitDocumentForReviewAction::class)->execute(
        version: $document->currentVersion,
        actor: $owner,
        steps: [[
            'name' => 'Reviewer',
            'assignee_type' => 'user',
            'assignees' => [$reviewer->id],
        ]],
    );

    $step = $document->currentVersion->workflow->steps()->firstOrFail();

    app(ApproveStepAction::class)->execute($step, $reviewer);

    expect(fn () => app(RejectStepAction::class)->execute($step->fresh(), $reviewer, 'Too late'))
        ->toThrow(\DomainException::class);
});
