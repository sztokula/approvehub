<?php

use App\Enums\UserRole;
use App\Models\Organization;
use App\Models\OrganizationUser;
use App\Models\Role;
use App\Models\User;

function attachOrgRole(User $user, Organization $organization, UserRole $role): void
{
    $roleModel = Role::query()->firstOrCreate(['name' => $role]);

    $user->organizations()->attach($organization->id, [
        'role_id' => $roleModel->id,
        'joined_at' => now(),
    ]);
}

it('allows organization admin to view and update member role', function (): void {
    $organization = Organization::factory()->create();
    $admin = User::factory()->create(['email_verified_at' => now()]);
    $member = User::factory()->create(['email_verified_at' => now()]);
    $viewerRole = Role::query()->firstOrCreate(['name' => UserRole::Viewer]);
    $editorRole = Role::query()->firstOrCreate(['name' => UserRole::Editor]);

    attachOrgRole($admin, $organization, UserRole::Admin);
    attachOrgRole($member, $organization, UserRole::Viewer);

    $membership = OrganizationUser::query()
        ->where('organization_id', $organization->id)
        ->where('user_id', $member->id)
        ->firstOrFail();

    $this->actingAs($admin)
        ->get(route('organizations.members.index', $organization))
        ->assertOk();

    $this->actingAs($admin)
        ->put(route('organizations.members.update', [$organization, $membership]), [
            'role_id' => $editorRole->id,
        ])
        ->assertRedirect(route('organizations.members.index', $organization));

    expect($membership->fresh()->role_id)->toBe($editorRole->id)
        ->and($viewerRole->id)->not()->toBe($editorRole->id);
});

it('forbids non admin from managing organization members', function (): void {
    $organization = Organization::factory()->create();
    $viewer = User::factory()->create(['email_verified_at' => now()]);
    attachOrgRole($viewer, $organization, UserRole::Viewer);

    $this->actingAs($viewer)
        ->get(route('organizations.members.index', $organization))
        ->assertForbidden();
});

it('prevents admin from removing own admin role', function (): void {
    $organization = Organization::factory()->create();
    $admin = User::factory()->create(['email_verified_at' => now()]);
    $viewerRole = Role::query()->firstOrCreate(['name' => UserRole::Viewer]);
    attachOrgRole($admin, $organization, UserRole::Admin);

    $membership = OrganizationUser::query()
        ->where('organization_id', $organization->id)
        ->where('user_id', $admin->id)
        ->firstOrFail();

    $this->actingAs($admin)
        ->put(route('organizations.members.update', [$organization, $membership]), [
            'role_id' => $viewerRole->id,
        ])
        ->assertUnprocessable();
});
