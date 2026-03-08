<?php

use App\Actions\Documents\CreateDocumentAction;
use App\Enums\UserRole;
use App\Jobs\DispatchWebhookEventJob;
use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Queue;

function attachWebhookMembership(User $user, Organization $organization, UserRole $role): void
{
    $roleModel = Role::query()->firstOrCreate(['name' => $role]);

    $user->organizations()->attach($organization->id, [
        'role_id' => $roleModel->id,
        'joined_at' => now(),
    ]);
}

it('dispatches webhook job for configured audit events', function (): void {
    Queue::fake();
    config()->set('approvehub.webhooks.enabled', true);
    config()->set('approvehub.webhooks.urls', ['https://example.test/webhook']);
    config()->set('approvehub.webhooks.events', ['document.created']);

    $organization = Organization::factory()->create();
    $owner = User::factory()->create(['email_verified_at' => now()]);
    attachWebhookMembership($owner, $organization, UserRole::Editor);

    app(CreateDocumentAction::class)->execute(
        organization: $organization,
        owner: $owner,
        title: 'Webhook doc',
        description: 'Desc',
        content: 'Body',
    );

    Queue::assertPushed(DispatchWebhookEventJob::class, function (DispatchWebhookEventJob $job): bool {
        return $job->event === 'document.created';
    });
});
