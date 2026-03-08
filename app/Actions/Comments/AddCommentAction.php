<?php

namespace App\Actions\Comments;

use App\Actions\Audit\RecordAuditLogAction;
use App\Models\Comment;
use App\Models\Document;
use App\Models\DocumentVersion;
use App\Models\User;
use DomainException;

/**
 * Handles AddCommentAction responsibilities for the ApproveHub domain.
 */
class AddCommentAction
{
    public function __construct(
        private readonly RecordAuditLogAction $recordAuditLogAction,
    ) {}

    public function execute(Document $document, User $actor, string $body, ?DocumentVersion $version = null): Comment
    {
        if ($version !== null && $version->document_id !== $document->id) {
            throw new DomainException('Version does not belong to the given document.');
        }

        $comment = $document->comments()->create([
            'version_id' => $version?->id,
            'user_id' => $actor->id,
            'body' => $body,
        ]);

        $this->recordAuditLogAction->execute(
            organizationId: $document->organization_id,
            actor: $actor,
            action: 'comment.added',
            targetType: 'comment',
            targetId: $comment->id,
            metadata: [
                'document_id' => $document->id,
                'version_id' => $version?->id,
            ],
        );

        return $comment;
    }
}
