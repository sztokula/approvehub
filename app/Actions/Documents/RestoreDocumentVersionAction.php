<?php

namespace App\Actions\Documents;

use App\Actions\Audit\RecordAuditLogAction;
use App\Models\Document;
use App\Models\DocumentVersion;
use App\Models\User;
use DomainException;

/**
 * Handles RestoreDocumentVersionAction responsibilities for the ApproveHub domain.
 */
class RestoreDocumentVersionAction
{
    public function __construct(
        private readonly CreateDocumentVersionAction $createDocumentVersionAction,
        private readonly RecordAuditLogAction $recordAuditLogAction,
    ) {}

    public function execute(Document $document, DocumentVersion $versionToRestore, User $actor): DocumentVersion
    {
        if ($versionToRestore->document_id !== $document->id) {
            throw new DomainException('Version does not belong to the given document.');
        }

        $restoredVersion = $this->createDocumentVersionAction->execute(
            document: $document,
            actor: $actor,
            titleSnapshot: $versionToRestore->title_snapshot,
            contentSnapshot: $versionToRestore->content_snapshot,
            metaSnapshot: $versionToRestore->meta_snapshot,
        );

        $this->recordAuditLogAction->execute(
            organizationId: $document->organization_id,
            actor: $actor,
            action: 'version.restored',
            targetType: 'document_version',
            targetId: $restoredVersion->id,
            metadata: [
                'document_id' => $document->id,
                'restored_from_version_id' => $versionToRestore->id,
                'restored_to_version_id' => $restoredVersion->id,
            ],
        );

        return $restoredVersion;
    }
}
