<?php

namespace App\Actions\Attachments;

use App\Actions\Audit\RecordAuditLogAction;
use App\Models\Attachment;
use App\Models\Document;
use App\Models\DocumentVersion;
use App\Models\User;
use DomainException;
use Illuminate\Http\UploadedFile;

/**
 * Handles UploadAttachmentAction responsibilities for the ApproveHub domain.
 */
class UploadAttachmentAction
{
    public function __construct(
        private readonly RecordAuditLogAction $recordAuditLogAction,
    ) {}

    public function execute(
        Document $document,
        User $actor,
        UploadedFile $file,
        ?string $disk = null,
        ?DocumentVersion $version = null,
    ): Attachment {
        if ($version !== null && $version->document_id !== $document->id) {
            throw new DomainException('Version does not belong to the given document.');
        }

        $resolvedDisk = $disk ?? (string) config('approvehub.attachments.disk', 'local');
        $storedPath = $file->store('attachments/'.$document->id, $resolvedDisk);

        $attachment = $document->attachments()->create([
            'version_id' => $version?->id,
            'uploaded_by' => $actor->id,
            'disk' => $resolvedDisk,
            'path' => $storedPath,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getClientMimeType() ?? $file->getMimeType() ?? 'application/octet-stream',
            'size_bytes' => $file->getSize() ?? 0,
            'checksum' => hash_file('sha256', $file->getRealPath()),
        ]);

        $this->recordAuditLogAction->execute(
            organizationId: $document->organization_id,
            actor: $actor,
            action: 'attachment.uploaded',
            targetType: 'attachment',
            targetId: $attachment->id,
            metadata: [
                'document_id' => $document->id,
                'version_id' => $version?->id,
                'path' => $storedPath,
            ],
        );

        return $attachment;
    }
}
