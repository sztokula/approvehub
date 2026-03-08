<?php

use App\Actions\Documents\CreateDocumentAction;
use App\Enums\UserRole;
use App\Jobs\GenerateDocumentPdfExportJob;
use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

function attachPdfMembership(User $user, Organization $organization, UserRole $role): void
{
    $roleModel = Role::query()->firstOrCreate(['name' => $role]);

    $user->organizations()->attach($organization->id, [
        'role_id' => $roleModel->id,
        'joined_at' => now(),
    ]);
}

it('queues async pdf export and returns pending token', function (): void {
    Queue::fake();

    $organization = Organization::factory()->create();
    $owner = User::factory()->create(['email_verified_at' => now()]);
    attachPdfMembership($owner, $organization, UserRole::Editor);

    $document = app(CreateDocumentAction::class)->execute(
        organization: $organization,
        owner: $owner,
        title: 'Async PDF',
        description: 'Desc',
        content: 'Body',
    );

    $response = $this->actingAs($owner)
        ->postJson(route('documents.pdf.exports.store', $document))
        ->assertAccepted()
        ->assertJsonPath('status', 'pending');

    Queue::assertPushed(GenerateDocumentPdfExportJob::class);

    $token = $response->json('token');
    expect(Cache::get("document_pdf_export:{$token}"))->not()->toBeNull();
});

it('downloads async pdf export when ready', function (): void {
    Storage::fake('local');

    $organization = Organization::factory()->create();
    $owner = User::factory()->create(['email_verified_at' => now()]);
    attachPdfMembership($owner, $organization, UserRole::Editor);

    $token = 'token-test-ready';
    $path = "exports/{$token}.pdf";
    Storage::disk('local')->put($path, '%PDF-1.4 test');

    Cache::put("document_pdf_export:{$token}", [
        'status' => 'ready',
        'requested_by' => $owner->id,
        'document_id' => 1,
        'path' => $path,
        'file_name' => 'document-1.pdf',
    ], now()->addHour());

    $this->actingAs($owner)
        ->get(route('documents.pdf.exports.show', $token))
        ->assertOk()
        ->assertHeader('content-disposition');
});
