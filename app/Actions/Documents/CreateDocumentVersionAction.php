<?php

namespace App\Actions\Documents;

use App\Actions\Audit\RecordAuditLogAction;
use App\Enums\DocumentStatus;
use App\Models\Document;
use App\Models\DocumentVersion;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Handles CreateDocumentVersionAction responsibilities for the ApproveHub domain.
 */
class CreateDocumentVersionAction
{
    public function __construct(
        private readonly RecordAuditLogAction $recordAuditLogAction,
    ) {}

    /**
     * @param  array<string, mixed>|null  $metaSnapshot
     */
    public function execute(
        Document $document,
        User $actor,
        string $titleSnapshot,
        string $contentSnapshot,
        ?array $metaSnapshot = null,
    ): DocumentVersion {
        return DB::transaction(function () use ($document, $actor, $titleSnapshot, $contentSnapshot, $metaSnapshot): DocumentVersion {
            $nextVersionNumber = (int) $document->versions()->max('version_number') + 1;

            $version = $document->versions()->create([
                'version_number' => $nextVersionNumber,
                'title_snapshot' => $titleSnapshot,
                'content_snapshot' => $contentSnapshot,
                'meta_snapshot' => $metaSnapshot,
                'created_by' => $actor->id,
            ]);

            $document->update([
                'current_version_id' => $version->id,
                'title' => $titleSnapshot,
                'status' => DocumentStatus::Draft,
            ]);

            $this->recordAuditLogAction->execute(
                organizationId: $document->organization_id,
                actor: $actor,
                action: 'version.created',
                targetType: 'document_version',
                targetId: $version->id,
                metadata: [
                    'document_id' => $document->id,
                    'version_number' => $version->version_number,
                ],
            );

            if ($nextVersionNumber > 1) {
                $this->recordAuditLogAction->execute(
                    organizationId: $document->organization_id,
                    actor: $actor,
                    action: 'document.updated',
                    targetType: 'document',
                    targetId: $document->id,
                    metadata: [
                        'document_id' => $document->id,
                        'current_version_id' => $version->id,
                    ],
                );
            }

            return $version;
        });
    }
}
