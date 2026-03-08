<?php

use App\Actions\Documents\CreateDocumentAction;
use App\Enums\UserRole;
use App\Models\Organization;
use App\Models\PublicShareLink;
use App\Models\Role;
use App\Models\User;

function attachShareLinkMembership(User $user, Organization $organization, UserRole $role): void
{
    $roleModel = Role::query()->firstOrCreate(['name' => $role]);

    $user->organizations()->attach($organization->id, [
        'role_id' => $roleModel->id,
        'joined_at' => now(),
    ]);
}

it('creates a public share link and allows read-only public access', function (): void {
    $owner = User::factory()->create(['email_verified_at' => now()]);
    $organization = Organization::factory()->create();
    attachShareLinkMembership($owner, $organization, UserRole::Editor);

    $document = app(CreateDocumentAction::class)->execute(
        organization: $organization,
        owner: $owner,
        title: 'Public Policy',
        description: 'External read',
        content: 'Current snapshot content',
    );

    $this->actingAs($owner)
        ->withHeader('Accept', 'application/json')
        ->post(route('documents.share-links.store', $document), [
            'expires_at' => now()->addDays(2)->toDateTimeString(),
        ])
        ->assertCreated();

    $link = PublicShareLink::query()->firstOrFail();

    $this->get(route('public-share-links.show', $link))
        ->assertOk()
        ->assertSee('Public Policy')
        ->assertSee('Current snapshot content');

    expect($organization->auditLogs()->where('action', 'share_link.created')->count())->toBe(1);
});

it('returns 404 for revoked public share link', function (): void {
    $owner = User::factory()->create(['email_verified_at' => now()]);
    $organization = Organization::factory()->create();
    attachShareLinkMembership($owner, $organization, UserRole::Editor);

    $document = app(CreateDocumentAction::class)->execute(
        organization: $organization,
        owner: $owner,
        title: 'Revoked document',
        description: 'Desc',
        content: 'Content',
    );

    $this->actingAs($owner)->post(route('documents.share-links.store', $document));
    $link = PublicShareLink::query()->firstOrFail();

    $this->actingAs($owner)
        ->delete(route('documents.share-links.destroy', [$document, $link]))
        ->assertRedirect(route('documents.show', $document));

    $this->get(route('public-share-links.show', $link->fresh()))
        ->assertNotFound();

    expect($organization->auditLogs()->where('action', 'share_link.revoked')->count())->toBe(1);
});

it('returns 404 for expired public share link', function (): void {
    $owner = User::factory()->create(['email_verified_at' => now()]);
    $organization = Organization::factory()->create();
    attachShareLinkMembership($owner, $organization, UserRole::Editor);

    $document = app(CreateDocumentAction::class)->execute(
        organization: $organization,
        owner: $owner,
        title: 'Expired document',
        description: 'Desc',
        content: 'Content',
    );

    $this->actingAs($owner)
        ->post(route('documents.share-links.store', $document), [
            'expires_at' => now()->addMinute()->toDateTimeString(),
        ]);

    $link = PublicShareLink::query()->firstOrFail();
    $link->update(['expires_at' => now()->subMinute()]);

    $this->get(route('public-share-links.show', $link))
        ->assertNotFound();
});

it('forbids creating share links across organizations', function (): void {
    $owner = User::factory()->create(['email_verified_at' => now()]);
    $intruder = User::factory()->create(['email_verified_at' => now()]);
    $organizationA = Organization::factory()->create();
    $organizationB = Organization::factory()->create();

    attachShareLinkMembership($owner, $organizationA, UserRole::Editor);
    attachShareLinkMembership($intruder, $organizationB, UserRole::Editor);

    $document = app(CreateDocumentAction::class)->execute(
        organization: $organizationA,
        owner: $owner,
        title: 'Scoped doc',
        description: 'Desc',
        content: 'Content',
    );

    $this->actingAs($intruder)
        ->post(route('documents.share-links.store', $document))
        ->assertForbidden();
});

it('rate limits public share link endpoint to reduce brute force risk', function (): void {
    config()->set('approvehub.rate_limits.public_share_links_per_minute', 1);

    $owner = User::factory()->create(['email_verified_at' => now()]);
    $organization = Organization::factory()->create();
    attachShareLinkMembership($owner, $organization, UserRole::Editor);

    $document = app(CreateDocumentAction::class)->execute(
        organization: $organization,
        owner: $owner,
        title: 'Limited document',
        description: 'Desc',
        content: 'Content',
    );

    $this->actingAs($owner)->post(route('documents.share-links.store', $document));
    $link = PublicShareLink::query()->firstOrFail();

    $this->get(route('public-share-links.show', $link))->assertOk();
    $this->get(route('public-share-links.show', $link))->assertTooManyRequests();
});

it('shares one limiter bucket per ip even for different tokens', function (): void {
    config()->set('approvehub.rate_limits.public_share_links_per_minute', 1);

    $this->get('/share/'.str_repeat('a', 64))->assertNotFound();
    $this->get('/share/'.str_repeat('b', 64))->assertTooManyRequests();
});
