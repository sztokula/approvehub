<?php

use App\Actions\Documents\CreateDocumentAction;
use App\Enums\DocumentVisibility;
use App\Enums\UserRole;
use App\Models\DocumentPermission;
use App\Models\Organization;
use App\Models\Role;
use App\Models\User;

function attachPermissionMembership(User $user, Organization $organization, UserRole $role): void
{
    $roleModel = Role::query()->firstOrCreate(['name' => $role]);

    $user->organizations()->attach($organization->id, [
        'role_id' => $roleModel->id,
        'joined_at' => now(),
    ]);
}

it('grants explicit view access for private document', function (): void {
    $organization = Organization::factory()->create();
    $owner = User::factory()->create(['email_verified_at' => now()]);
    $viewer = User::factory()->create(['email_verified_at' => now()]);
    attachPermissionMembership($owner, $organization, UserRole::Editor);
    attachPermissionMembership($viewer, $organization, UserRole::Viewer);

    $document = app(CreateDocumentAction::class)->execute(
        organization: $organization,
        owner: $owner,
        title: 'Private with explicit access',
        description: 'Desc',
        content: 'Body',
        visibility: DocumentVisibility::Private,
    );

    $this->actingAs($owner)
        ->post(route('documents.permissions.store', $document), [
            'user_id' => $viewer->id,
            'permission' => 'view',
        ])
        ->assertRedirect(route('documents.show', $document));

    $this->actingAs($viewer)
        ->get(route('documents.show', $document))
        ->assertOk();
});

it('forbids granting access to user outside organization', function (): void {
    $organization = Organization::factory()->create();
    $owner = User::factory()->create(['email_verified_at' => now()]);
    $external = User::factory()->create(['email_verified_at' => now()]);
    attachPermissionMembership($owner, $organization, UserRole::Editor);

    $document = app(CreateDocumentAction::class)->execute(
        organization: $organization,
        owner: $owner,
        title: 'Private',
        description: 'Desc',
        content: 'Body',
    );

    $this->actingAs($owner)
        ->post(route('documents.permissions.store', $document), [
            'user_id' => $external->id,
            'permission' => 'view',
        ])
        ->assertSessionHasErrors(['user_id']);
});

it('revokes document permission', function (): void {
    $organization = Organization::factory()->create();
    $owner = User::factory()->create(['email_verified_at' => now()]);
    $viewer = User::factory()->create(['email_verified_at' => now()]);
    attachPermissionMembership($owner, $organization, UserRole::Editor);
    attachPermissionMembership($viewer, $organization, UserRole::Viewer);

    $document = app(CreateDocumentAction::class)->execute(
        organization: $organization,
        owner: $owner,
        title: 'Permission revoke',
        description: 'Desc',
        content: 'Body',
        visibility: DocumentVisibility::Private,
    );

    $permission = DocumentPermission::query()->create([
        'document_id' => $document->id,
        'user_id' => $viewer->id,
        'permission' => 'view',
    ]);

    $this->actingAs($owner)
        ->delete(route('documents.permissions.destroy', [$document, $permission]))
        ->assertRedirect(route('documents.show', $document));

    $this->assertDatabaseMissing('document_permissions', [
        'id' => $permission->id,
    ]);
});
