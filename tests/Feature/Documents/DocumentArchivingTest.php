<?php

use App\Actions\Documents\CreateDocumentAction;
use App\Enums\DocumentStatus;
use App\Enums\UserRole;
use App\Models\Organization;
use App\Models\Role;
use App\Models\User;

function attachArchivingMembership(User $user, Organization $organization, UserRole $role): void
{
    $roleModel = Role::query()->firstOrCreate(['name' => $role]);

    $user->organizations()->attach($organization->id, [
        'role_id' => $roleModel->id,
        'joined_at' => now(),
    ]);
}

it('allows admin to archive approved document and writes audit event', function (): void {
    $organization = Organization::factory()->create();
    $admin = User::factory()->create(['email_verified_at' => now()]);
    $owner = User::factory()->create(['email_verified_at' => now()]);

    attachArchivingMembership($admin, $organization, UserRole::Admin);
    attachArchivingMembership($owner, $organization, UserRole::Editor);

    $document = app(CreateDocumentAction::class)->execute(
        organization: $organization,
        owner: $owner,
        title: 'Approved Doc',
        description: 'Ready to archive',
        content: 'Body',
    );

    $document->update([
        'status' => DocumentStatus::Approved,
    ]);

    $this->actingAs($admin)
        ->post(route('documents.archive.store', $document))
        ->assertRedirect(route('documents.show', $document));

    expect($document->fresh()->status)->toBe(DocumentStatus::Archived)
        ->and($organization->auditLogs()->where('action', 'document.archived')->count())->toBe(1);
});

it('rejects archiving when document is not approved', function (): void {
    $organization = Organization::factory()->create();
    $admin = User::factory()->create(['email_verified_at' => now()]);
    $owner = User::factory()->create(['email_verified_at' => now()]);

    attachArchivingMembership($admin, $organization, UserRole::Admin);
    attachArchivingMembership($owner, $organization, UserRole::Editor);

    $document = app(CreateDocumentAction::class)->execute(
        organization: $organization,
        owner: $owner,
        title: 'Draft Doc',
        description: 'Not approved',
        content: 'Body',
    );

    $this->actingAs($admin)
        ->withHeader('Accept', 'application/json')
        ->post(route('documents.archive.store', $document))
        ->assertUnprocessable();

    expect($document->fresh()->status)->toBe(DocumentStatus::Draft);
});

it('forbids non admin from archiving approved document', function (): void {
    $organization = Organization::factory()->create();
    $owner = User::factory()->create(['email_verified_at' => now()]);
    $editor = User::factory()->create(['email_verified_at' => now()]);

    attachArchivingMembership($owner, $organization, UserRole::Editor);
    attachArchivingMembership($editor, $organization, UserRole::Editor);

    $document = app(CreateDocumentAction::class)->execute(
        organization: $organization,
        owner: $owner,
        title: 'Approved Doc',
        description: 'Ready to archive',
        content: 'Body',
    );

    $document->update([
        'status' => DocumentStatus::Approved,
    ]);

    $this->actingAs($editor)
        ->post(route('documents.archive.store', $document))
        ->assertForbidden();
});
