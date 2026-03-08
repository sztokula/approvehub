<?php

namespace App\Actions\Documents;

use App\Actions\Audit\RecordAuditLogAction;
use App\Enums\DocumentVisibility;
use App\Models\Document;
use App\Models\User;

/**
 * Handles UpdateDocumentVisibilityAction responsibilities for the ApproveHub domain.
 */
class UpdateDocumentVisibilityAction
{
    public function __construct(
        private readonly RecordAuditLogAction $recordAuditLogAction,
    ) {}

    public function execute(Document $document, User $actor, DocumentVisibility $visibility): void
    {
        if ($document->visibility === $visibility) {
            return;
        }

        $fromVisibility = $document->visibility;

        $document->update([
            'visibility' => $visibility,
        ]);

        $this->recordAuditLogAction->execute(
            organizationId: $document->organization_id,
            actor: $actor,
            action: 'document.visibility_changed',
            targetType: 'document',
            targetId: $document->id,
            metadata: [
                'document_id' => $document->id,
                'from_visibility' => $fromVisibility->value,
                'to_visibility' => $visibility->value,
            ],
        );
    }
}
