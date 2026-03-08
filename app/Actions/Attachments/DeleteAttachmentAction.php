<?php

namespace App\Actions\Attachments;

use App\Actions\Audit\RecordAuditLogAction;
use App\Models\Attachment;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

/**
 * Handles DeleteAttachmentAction responsibilities for the ApproveHub domain.
 */
class DeleteAttachmentAction
{
    public function __construct(
        private readonly RecordAuditLogAction $recordAuditLogAction,
    ) {}

    public function execute(Attachment $attachment, User $actor): void
    {
        Storage::disk($attachment->disk)->delete($attachment->path);

        $document = $attachment->document;

        $this->recordAuditLogAction->execute(
            organizationId: $document->organization_id,
            actor: $actor,
            action: 'attachment.deleted',
            targetType: 'attachment',
            targetId: $attachment->id,
            metadata: [
                'document_id' => $document->id,
                'path' => $attachment->path,
            ],
        );

        $attachment->delete();
    }
}
