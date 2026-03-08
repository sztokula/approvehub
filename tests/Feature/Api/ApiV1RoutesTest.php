<?php

use App\Actions\Documents\CreateDocumentAction;
use App\Enums\UserRole;
use App\Models\Organization;
use App\Models\Role;
use App\Models\User;

function attachApiMembership(User $user, Organization $organization, UserRole $role): void
{
    $roleModel = Role::query()->firstOrCreate(['name' => $role]);

    $user->organizations()->attach($organization->id, [
        'role_id' => $roleModel->id,
        'joined_at' => now(),
    ]);
}

it('creates document via api v1 endpoint', function (): void {
    $organization = Organization::factory()->create();
    $user = User::factory()->create(['email_verified_at' => now()]);
    attachApiMembership($user, $organization, UserRole::Editor);

    $this->actingAs($user)
        ->postJson('/api/v1/documents', [
            'organization_id' => $organization->id,
            'title' => 'API Document',
            'description' => 'API',
            'document_type' => 'general',
            'content' => 'Body',
            'visibility' => 'private',
        ])
        ->assertCreated()
        ->assertJsonPath('title', 'API Document');
});

it('returns audit payload via api v1 endpoint', function (): void {
    $organization = Organization::factory()->create();
    $owner = User::factory()->create(['email_verified_at' => now()]);
    attachApiMembership($owner, $organization, UserRole::Editor);

    $document = app(CreateDocumentAction::class)->execute(
        organization: $organization,
        owner: $owner,
        title: 'API Audited Document',
        description: 'Desc',
        content: 'Body',
    );

    $this->actingAs($owner)
        ->getJson("/api/v1/documents/{$document->id}/audit")
        ->assertOk()
        ->assertJsonFragment([
            'action' => 'document.created',
        ]);
});
