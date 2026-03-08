<?php

use App\Actions\Documents\CreateDocumentAction;
use App\Actions\Documents\SubmitDocumentForReviewAction;
use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use App\Notifications\ReviewEscalatedNotification;
use Illuminate\Support\Facades\Notification;

it('escalates overdue active steps to admins and logs audit', function (): void {
    Notification::fake();

    $organization = Organization::factory()->create();
    $owner = User::factory()->create();
    $admin = User::factory()->create();
    $editorRole = Role::query()->firstOrCreate(['name' => UserRole::Editor]);
    $adminRole = Role::query()->firstOrCreate(['name' => UserRole::Admin]);

    $owner->organizations()->attach($organization->id, ['role_id' => $editorRole->id, 'joined_at' => now()]);
    $admin->organizations()->attach($organization->id, ['role_id' => $adminRole->id, 'joined_at' => now()]);

    $document = app(CreateDocumentAction::class)->execute(
        organization: $organization,
        owner: $owner,
        title: 'Escalation doc',
        description: 'Desc',
        content: 'Content',
    );

    app(SubmitDocumentForReviewAction::class)->execute(
        version: $document->currentVersion,
        actor: $owner,
        steps: [[
            'name' => 'Admin approval',
            'assignee_type' => 'role',
            'assignee_role' => UserRole::Admin->value,
            'due_at' => now()->subHours(30)->toDateTimeString(),
        ]],
    );

    $this->artisan('review:escalate-overdue --hours=24')
        ->assertSuccessful();

    Notification::assertSentTo($admin, ReviewEscalatedNotification::class);
    expect(AuditLog::query()->where('action', 'review.escalated')->count())->toBe(1);

    $this->artisan('review:escalate-overdue --hours=24')
        ->assertSuccessful();

    expect(AuditLog::query()->where('action', 'review.escalated')->count())->toBe(1);
});
