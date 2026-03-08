<?php

use App\Actions\Documents\CreateDocumentAction;
use App\Enums\UserRole;
use App\Models\Organization;
use App\Models\Role;
use App\Models\User;

function attachUxMembership(User $user, Organization $organization, UserRole $role): void
{
    $roleModel = Role::query()->firstOrCreate(['name' => $role]);

    $user->organizations()->attach($organization->id, [
        'role_id' => $roleModel->id,
        'joined_at' => now(),
    ]);
}

it('renders rich text editor on document create view', function (): void {
    $organization = Organization::factory()->create();
    $owner = User::factory()->create(['email_verified_at' => now()]);
    attachUxMembership($owner, $organization, UserRole::Editor);

    $this->actingAs($owner)
        ->get(route('documents.index'))
        ->assertOk()
        ->assertSee('<trix-editor', false)
        ->assertSee('New Document')
        ->assertSee('Show Filters');
});

it('renders collapsible content sections and reviewer selector on document details view', function (): void {
    $organization = Organization::factory()->create();
    $owner = User::factory()->create(['email_verified_at' => now()]);
    $reviewer = User::factory()->create(['email_verified_at' => now()]);
    attachUxMembership($owner, $organization, UserRole::Editor);
    attachUxMembership($reviewer, $organization, UserRole::Reviewer);

    $document = app(CreateDocumentAction::class)->execute(
        organization: $organization,
        owner: $owner,
        title: 'UX document',
        description: 'Desc',
        content: 'Body',
    );

    $this->actingAs($owner)
        ->get(route('documents.show', $document))
        ->assertOk()
        ->assertSee('Approval Steps')
        ->assertSee('Version History')
        ->assertSee('Collaboration')
        ->assertSee('Manual Reviewers')
        ->assertSee('Workbench')
        ->assertSee('Audit');
});
