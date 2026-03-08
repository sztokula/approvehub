<?php

namespace App\Actions\Documents;

use App\Actions\Audit\RecordAuditLogAction;
use App\Enums\DocumentStatus;
use App\Models\Document;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Handles ArchiveDocumentAction responsibilities for the ApproveHub domain.
 */
class ArchiveDocumentAction
{
    public function __construct(
        private readonly RecordAuditLogAction $recordAuditLogAction,
    ) {}

    public function execute(Document $document, User $actor): void
    {
        DB::transaction(function () use ($document, $actor): void {
            $lockedDocument = Document::query()->lockForUpdate()->findOrFail($document->id);

            if ($lockedDocument->status !== DocumentStatus::Approved) {
                throw new DomainException('Only approved documents can be archived.');
            }

            $lockedDocument->update([
                'status' => DocumentStatus::Archived,
            ]);

            $this->recordAuditLogAction->execute(
                organizationId: $lockedDocument->organization_id,
                actor: $actor,
                action: 'document.archived',
                targetType: 'document',
                targetId: $lockedDocument->id,
                metadata: [
                    'from_status' => DocumentStatus::Approved->value,
                    'to_status' => DocumentStatus::Archived->value,
                ],
            );
        });
    }
}
