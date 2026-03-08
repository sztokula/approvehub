<?php

namespace App\Actions\Documents;

use App\Actions\Audit\RecordAuditLogAction;
use App\Enums\DocumentStatus;
use App\Enums\DocumentVisibility;
use App\Models\Document;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Handles CreateDocumentAction responsibilities for the ApproveHub domain.
 */
class CreateDocumentAction
{
    public function __construct(
        private readonly CreateDocumentVersionAction $createDocumentVersionAction,
        private readonly RecordAuditLogAction $recordAuditLogAction,
    ) {}

    /**
     * @param  array<string, mixed>|null  $metaSnapshot
     */
    public function execute(
        Organization $organization,
        User $owner,
        string $title,
        string $description,
        string $content,
        string $documentType = 'general',
        DocumentVisibility $visibility = DocumentVisibility::Private,
        ?array $metaSnapshot = null,
    ): Document {
        return DB::transaction(function () use ($organization, $owner, $title, $description, $documentType, $content, $visibility, $metaSnapshot): Document {
            $document = Document::query()->create([
                'organization_id' => $organization->id,
                'owner_id' => $owner->id,
                'title' => $title,
                'description' => $description,
                'document_type' => $documentType,
                'visibility' => $visibility,
                'status' => DocumentStatus::Draft,
            ]);

            $version = $this->createDocumentVersionAction->execute(
                document: $document,
                actor: $owner,
                titleSnapshot: $title,
                contentSnapshot: $content,
                metaSnapshot: $metaSnapshot,
            );

            $document->update([
                'current_version_id' => $version->id,
            ]);

            $this->recordAuditLogAction->execute(
                organizationId: $organization->id,
                actor: $owner,
                action: 'document.created',
                targetType: 'document',
                targetId: $document->id,
                metadata: [
                    'current_version_id' => $version->id,
                ],
            );

            return $document->fresh(['currentVersion']);
        });
    }
}
