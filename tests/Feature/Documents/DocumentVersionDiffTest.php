<?php

use App\Actions\Documents\CreateDocumentAction;
use App\Actions\Documents\CreateDocumentVersionAction;
use App\Enums\UserRole;
use App\Models\Organization;
use App\Models\Role;
use App\Models\User;

function attachDiffMembership(User $user, Organization $organization, UserRole $role): void
{
    $roleModel = Role::query()->firstOrCreate(['name' => $role]);

    $user->organizations()->attach($organization->id, [
        'role_id' => $roleModel->id,
        'joined_at' => now(),
    ]);
}

it('shows version diff for two snapshots of the same document', function (): void {
    $owner = User::factory()->create(['email_verified_at' => now()]);
    $organization = Organization::factory()->create();
    attachDiffMembership($owner, $organization, UserRole::Editor);

    $document = app(CreateDocumentAction::class)->execute(
        organization: $organization,
        owner: $owner,
        title: 'Diff doc',
        description: 'Desc',
        content: "Line one\nLine two",
    );

    $versionTwo = app(CreateDocumentVersionAction::class)->execute(
        document: $document->fresh(),
        actor: $owner,
        titleSnapshot: 'Diff doc v2',
        contentSnapshot: "Line one\nLine changed\nLine three",
    );

    $versionOne = $document->versions()->where('version_number', 1)->firstOrFail();

    $this->actingAs($owner)
        ->get(route('documents.versions.diff', [
            'document' => $document,
            'from_version_id' => $versionOne->id,
            'to_version_id' => $versionTwo->id,
        ]))
        ->assertOk()
        ->assertSee('Version Diff')
        ->assertSee('Line changed')
        ->assertSee('Line three');
});

it('forbids diff access across organizations', function (): void {
    $owner = User::factory()->create(['email_verified_at' => now()]);
    $intruder = User::factory()->create(['email_verified_at' => now()]);
    $organizationA = Organization::factory()->create();
    $organizationB = Organization::factory()->create();

    attachDiffMembership($owner, $organizationA, UserRole::Editor);
    attachDiffMembership($intruder, $organizationB, UserRole::Viewer);

    $document = app(CreateDocumentAction::class)->execute(
        organization: $organizationA,
        owner: $owner,
        title: 'Private doc',
        description: 'Desc',
        content: 'Line',
    );

    $versionOne = $document->versions()->where('version_number', 1)->firstOrFail();
    $versionTwo = app(CreateDocumentVersionAction::class)->execute(
        document: $document->fresh(),
        actor: $owner,
        titleSnapshot: 'Private doc v2',
        contentSnapshot: 'Line changed',
    );

    $this->actingAs($intruder)
        ->get(route('documents.versions.diff', [
            'document' => $document,
            'from_version_id' => $versionOne->id,
            'to_version_id' => $versionTwo->id,
        ]))
        ->assertForbidden();
});

it('includes metadata diff when versions have different meta snapshot', function (): void {
    $owner = User::factory()->create(['email_verified_at' => now()]);
    $organization = Organization::factory()->create();
    attachDiffMembership($owner, $organization, UserRole::Editor);

    $document = app(CreateDocumentAction::class)->execute(
        organization: $organization,
        owner: $owner,
        title: 'Metadata diff doc',
        description: 'Desc',
        content: 'Line one',
        metaSnapshot: ['format' => 'markdown', 'risk' => 'low'],
    );

    $versionTwo = app(CreateDocumentVersionAction::class)->execute(
        document: $document->fresh(),
        actor: $owner,
        titleSnapshot: 'Metadata diff doc v2',
        contentSnapshot: 'Line one updated',
        metaSnapshot: ['format' => 'html', 'risk' => 'high'],
    );

    $versionOne = $document->versions()->where('version_number', 1)->firstOrFail();

    $this->actingAs($owner)
        ->getJson(route('documents.versions.diff', [
            'document' => $document,
            'from_version_id' => $versionOne->id,
            'to_version_id' => $versionTwo->id,
        ]))
        ->assertOk()
        ->assertJsonPath('diff.metadata.0.key', 'format')
        ->assertJsonPath('diff.metadata.0.changed', true);
});
