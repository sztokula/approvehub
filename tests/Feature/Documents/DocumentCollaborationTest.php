<?php

use App\Actions\Documents\CreateDocumentAction;
use App\Enums\UserRole;
use App\Models\Attachment;
use App\Models\Comment;
use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

function attachUserToOrganization(User $user, Organization $organization, UserRole $role): void
{
    $roleModel = Role::query()->firstOrCreate(['name' => $role]);

    $user->organizations()->attach($organization->id, [
        'role_id' => $roleModel->id,
        'joined_at' => now(),
    ]);
}

it('forbids cross-organization document access', function (): void {
    $owner = User::factory()->create(['email_verified_at' => now()]);
    $intruder = User::factory()->create(['email_verified_at' => now()]);
    $organizationA = Organization::factory()->create();
    $organizationB = Organization::factory()->create();

    attachUserToOrganization($owner, $organizationA, UserRole::Editor);
    attachUserToOrganization($intruder, $organizationB, UserRole::Viewer);

    $document = app(CreateDocumentAction::class)->execute(
        organization: $organizationA,
        owner: $owner,
        title: 'Org A policy',
        description: 'Private',
        content: 'secret',
    );

    $this->actingAs($intruder)
        ->get(route('documents.show', $document))
        ->assertForbidden();
});

it('adds comments and records audit event', function (): void {
    $user = User::factory()->create(['email_verified_at' => now()]);
    $organization = Organization::factory()->create();
    attachUserToOrganization($user, $organization, UserRole::Editor);

    $document = app(CreateDocumentAction::class)->execute(
        organization: $organization,
        owner: $user,
        title: 'Spec',
        description: 'desc',
        content: 'content',
    );

    $this->actingAs($user)
        ->postJson(route('documents.comments.store', $document), [
            'body' => 'Looks good',
            'version_id' => $document->current_version_id,
        ])
        ->assertCreated();

    expect(Comment::query()->count())->toBe(1)
        ->and($organization->auditLogs()->where('action', 'comment.added')->count())->toBe(1);
});

it('uploads and deletes attachments with audit events', function (): void {
    Storage::fake('local');

    $user = User::factory()->create(['email_verified_at' => now()]);
    $organization = Organization::factory()->create();
    attachUserToOrganization($user, $organization, UserRole::Editor);

    $document = app(CreateDocumentAction::class)->execute(
        organization: $organization,
        owner: $user,
        title: 'Attachment doc',
        description: 'desc',
        content: 'content',
    );

    $this->actingAs($user)
        ->withHeader('Accept', 'application/json')
        ->post(route('documents.attachments.store', $document), [
            'file' => UploadedFile::fake()->create('file.pdf', 64, 'application/pdf'),
            'version_id' => $document->current_version_id,
        ])
        ->assertCreated();

    $attachment = Attachment::query()->firstOrFail();

    Storage::disk('local')->assertExists($attachment->path);
    expect($organization->auditLogs()->where('action', 'attachment.uploaded')->count())->toBe(1);

    $this->actingAs($user)
        ->deleteJson(route('documents.attachments.destroy', [$document, $attachment]))
        ->assertOk();

    Storage::disk('local')->assertMissing($attachment->path);
    expect($organization->auditLogs()->where('action', 'attachment.deleted')->count())->toBe(1);
});
