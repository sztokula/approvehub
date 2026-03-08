<?php

use App\Actions\Comments\AddCommentAction;
use App\Actions\Documents\CreateDocumentAction;
use App\Actions\Documents\CreateDocumentVersionAction;
use App\Actions\Documents\SubmitDocumentForReviewAction;
use App\Enums\UserRole;
use App\Models\Document;
use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Carbon;

function attachMembership(User $user, Organization $organization, UserRole $role): void
{
    $roleModel = Role::query()->firstOrCreate(['name' => $role]);

    $user->organizations()->attach($organization->id, [
        'role_id' => $roleModel->id,
        'joined_at' => now(),
    ]);
}

it('filters documents by owner reviewer and updated date range', function (): void {
    $organization = Organization::factory()->create();
    $ownerA = User::factory()->create(['email_verified_at' => now()]);
    $ownerB = User::factory()->create(['email_verified_at' => now()]);
    $reviewer = User::factory()->create(['email_verified_at' => now()]);

    attachMembership($ownerA, $organization, UserRole::Editor);
    attachMembership($ownerB, $organization, UserRole::Editor);
    attachMembership($reviewer, $organization, UserRole::Reviewer);

    $docA = app(CreateDocumentAction::class)->execute(
        organization: $organization,
        owner: $ownerA,
        title: 'Doc A',
        description: 'A',
        content: 'A',
    );

    $docB = app(CreateDocumentAction::class)->execute(
        organization: $organization,
        owner: $ownerB,
        title: 'Doc B',
        description: 'B',
        content: 'B',
    );

    app(SubmitDocumentForReviewAction::class)->execute(
        version: $docA->currentVersion,
        actor: $ownerA,
        steps: [[
            'name' => 'Reviewer',
            'assignee_type' => 'user',
            'assignees' => [$reviewer->id],
        ]],
    );

    Document::query()->whereKey($docA->id)->update(['updated_at' => Carbon::parse('2026-01-10 10:00:00')]);
    Document::query()->whereKey($docB->id)->update(['updated_at' => Carbon::parse('2026-02-10 10:00:00')]);

    $response = $this->actingAs($ownerA)->getJson(route('documents.index', [
        'owner_id' => $ownerA->id,
        'reviewer_id' => $reviewer->id,
        'updated_from' => '2026-01-01',
        'updated_to' => '2026-01-31',
    ]));

    $response->assertOk();
    $titles = collect($response->json('data'))->pluck('title');

    expect($titles)->toContain('Doc A')
        ->and($titles)->not()->toContain('Doc B');
});

it('returns document details with audit logs payload', function (): void {
    $organization = Organization::factory()->create();
    $owner = User::factory()->create(['email_verified_at' => now()]);
    attachMembership($owner, $organization, UserRole::Editor);

    $document = app(CreateDocumentAction::class)->execute(
        organization: $organization,
        owner: $owner,
        title: 'Audited Document',
        description: 'Desc',
        content: 'Content',
    );

    $response = $this->actingAs($owner)->getJson(route('documents.show', $document));

    $response->assertOk()
        ->assertJsonPath('document.title', 'Audited Document');

    expect($response->json('audit_logs'))->not()->toBeEmpty();
});

it('exports audit trail in json and csv formats', function (): void {
    $organization = Organization::factory()->create();
    $owner = User::factory()->create(['email_verified_at' => now()]);
    attachMembership($owner, $organization, UserRole::Editor);

    $document = app(CreateDocumentAction::class)->execute(
        organization: $organization,
        owner: $owner,
        title: 'Exported Audit',
        description: 'Desc',
        content: 'Content',
    );

    $this->actingAs($owner)
        ->get(route('documents.audit.export', [$document, 'format' => 'json']))
        ->assertOk()
        ->assertHeader('content-disposition');

    $this->actingAs($owner)
        ->get(route('documents.audit.export', [$document, 'format' => 'csv']))
        ->assertOk()
        ->assertHeader('content-disposition');
});

it('exports document as pdf', function (): void {
    $organization = Organization::factory()->create();
    $owner = User::factory()->create(['email_verified_at' => now()]);
    attachMembership($owner, $organization, UserRole::Editor);

    $document = app(CreateDocumentAction::class)->execute(
        organization: $organization,
        owner: $owner,
        title: 'PDF Document',
        description: 'Desc',
        content: "Line one\nLine two",
    );

    app(CreateDocumentVersionAction::class)->execute(
        document: $document->fresh(),
        actor: $owner,
        titleSnapshot: 'PDF Document v2',
        contentSnapshot: "Line one\nLine two\nLine three",
    );

    app(AddCommentAction::class)->execute(
        document: $document->fresh(),
        actor: $owner,
        body: 'Review note',
        version: $document->fresh()->currentVersion,
    );

    $response = $this->actingAs($owner)
        ->get(route('documents.pdf.export', $document))
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf')
        ->assertHeader('content-disposition');

    $content = (string) $response->getContent();

    expect($content)->toContain('ApproveHub Document Export')
        ->and($content)->toContain('Document Overview')
        ->and($content)->toContain('Current Version')
        ->and($content)->toContain('Versions')
        ->and($content)->toContain('Comments')
        ->and($content)->toContain('Audit Timeline');
});
