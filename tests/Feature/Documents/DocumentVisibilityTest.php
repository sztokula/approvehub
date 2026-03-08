<?php

use App\Actions\Documents\CreateDocumentAction;
use App\Enums\DocumentVisibility;
use App\Enums\UserRole;
use App\Models\Organization;
use App\Models\Role;
use App\Models\User;

function attachVisibilityMembership(User $user, Organization $organization, UserRole $role): void
{
    $roleModel = Role::query()->firstOrCreate(['name' => $role]);

    $user->organizations()->attach($organization->id, [
        'role_id' => $roleModel->id,
        'joined_at' => now(),
    ]);
}

it('updates document visibility and writes audit event', function (): void {
    $organization = Organization::factory()->create();
    $owner = User::factory()->create(['email_verified_at' => now()]);
    attachVisibilityMembership($owner, $organization, UserRole::Editor);

    $document = app(CreateDocumentAction::class)->execute(
        organization: $organization,
        owner: $owner,
        title: 'Visibility doc',
        description: 'Desc',
        content: 'Body',
        visibility: DocumentVisibility::Private,
    );

    $this->actingAs($owner)
        ->put(route('documents.visibility.update', $document), [
            'visibility' => DocumentVisibility::Organization->value,
        ])
        ->assertRedirect(route('documents.show', $document));

    expect($document->fresh()->visibility)->toBe(DocumentVisibility::Organization)
        ->and($organization->auditLogs()->where('action', 'document.visibility_changed')->count())->toBe(1);
});

it('forbids user outside document organization from changing visibility', function (): void {
    $organizationA = Organization::factory()->create();
    $organizationB = Organization::factory()->create();
    $owner = User::factory()->create(['email_verified_at' => now()]);
    $intruder = User::factory()->create(['email_verified_at' => now()]);

    attachVisibilityMembership($owner, $organizationA, UserRole::Editor);
    attachVisibilityMembership($intruder, $organizationB, UserRole::Editor);

    $document = app(CreateDocumentAction::class)->execute(
        organization: $organizationA,
        owner: $owner,
        title: 'Visibility doc',
        description: 'Desc',
        content: 'Body',
    );

    $this->actingAs($intruder)
        ->put(route('documents.visibility.update', $document), [
            'visibility' => DocumentVisibility::Organization->value,
        ])
        ->assertForbidden();
});
