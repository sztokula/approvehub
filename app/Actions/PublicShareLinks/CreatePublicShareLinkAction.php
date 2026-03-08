<?php

namespace App\Actions\PublicShareLinks;

use App\Actions\Audit\RecordAuditLogAction;
use App\Models\Document;
use App\Models\PublicShareLink;
use App\Models\User;

/**
 * Handles CreatePublicShareLinkAction responsibilities for the ApproveHub domain.
 */
class CreatePublicShareLinkAction
{
    public function __construct(
        private readonly RecordAuditLogAction $recordAuditLogAction,
    ) {}

    public function execute(Document $document, User $actor, ?string $expiresAt = null): PublicShareLink
    {
        do {
            $token = bin2hex(random_bytes(32));
        } while (PublicShareLink::query()->where('token', $token)->exists());

        $publicShareLink = $document->publicShareLinks()->create([
            'created_by' => $actor->id,
            'token' => $token,
            'expires_at' => $expiresAt,
            'is_active' => true,
        ]);

        $this->recordAuditLogAction->execute(
            organizationId: $document->organization_id,
            actor: $actor,
            action: 'share_link.created',
            targetType: 'public_share_link',
            targetId: $publicShareLink->id,
            metadata: [
                'document_id' => $document->id,
                'expires_at' => $publicShareLink->expires_at?->toIso8601String(),
            ],
        );

        return $publicShareLink;
    }
}
