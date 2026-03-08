<?php

namespace App\Actions\PublicShareLinks;

use App\Actions\Audit\RecordAuditLogAction;
use App\Models\PublicShareLink;
use App\Models\User;

/**
 * Handles RevokePublicShareLinkAction responsibilities for the ApproveHub domain.
 */
class RevokePublicShareLinkAction
{
    public function __construct(
        private readonly RecordAuditLogAction $recordAuditLogAction,
    ) {}

    public function execute(PublicShareLink $publicShareLink, User $actor): void
    {
        $publicShareLink->update([
            'is_active' => false,
        ]);

        $document = $publicShareLink->document;

        $this->recordAuditLogAction->execute(
            organizationId: $document->organization_id,
            actor: $actor,
            action: 'share_link.revoked',
            targetType: 'public_share_link',
            targetId: $publicShareLink->id,
            metadata: [
                'document_id' => $document->id,
                'expires_at' => $publicShareLink->expires_at?->toIso8601String(),
            ],
        );
    }
}
