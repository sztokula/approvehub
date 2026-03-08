<?php

use App\Actions\Documents\CreateDocumentAction;
use App\Actions\Documents\SubmitDocumentForReviewAction;
use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use App\Notifications\ReviewReminderNotification;
use Illuminate\Support\Facades\Notification;

it('sends reminder for active step nearing deadline and logs audit', function (): void {
    Notification::fake();

    $organization = Organization::factory()->create();
    $owner = User::factory()->create();
    $reviewer = User::factory()->create();
    $editorRole = Role::query()->firstOrCreate(['name' => UserRole::Editor]);
    $reviewerRole = Role::query()->firstOrCreate(['name' => UserRole::Reviewer]);

    $owner->organizations()->attach($organization->id, ['role_id' => $editorRole->id, 'joined_at' => now()]);
    $reviewer->organizations()->attach($organization->id, ['role_id' => $reviewerRole->id, 'joined_at' => now()]);

    $document = app(CreateDocumentAction::class)->execute(
        organization: $organization,
        owner: $owner,
        title: 'Reminder doc',
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
            'due_at' => now()->addHours(2)->toDateTimeString(),
        ]],
    );

    $this->artisan('review:send-reminders --hours=24')
        ->assertSuccessful();

    Notification::assertSentTo($reviewer, ReviewReminderNotification::class);
    expect(AuditLog::query()->where('action', 'review.reminder_sent')->count())->toBe(1);

    $this->artisan('review:send-reminders --hours=24')
        ->assertSuccessful();

    expect(AuditLog::query()->where('action', 'review.reminder_sent')->count())->toBe(1);
});
