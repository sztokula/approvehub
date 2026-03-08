<?php

namespace App\Actions\Audit;

use App\Jobs\DispatchWebhookEventJob;
use App\Models\AuditLog;
use App\Models\User;

/**
 * Handles RecordAuditLogAction responsibilities for the ApproveHub domain.
 */
class RecordAuditLogAction
{
    /**
     * @param  array<string, mixed>|null  $metadata
     */
    public function execute(
        int $organizationId,
        ?User $actor,
        string $action,
        string $targetType,
        int $targetId,
        ?array $metadata = null,
        ?string $ipAddress = null,
        ?string $userAgent = null,
    ): AuditLog {
        $auditLog = AuditLog::query()->create([
            'organization_id' => $organizationId,
            'actor_id' => $actor?->id,
            'action' => $action,
            'target_type' => $targetType,
            'target_id' => $targetId,
            'metadata' => $metadata,
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
            'occurred_at' => now(),
        ]);

        if ($this->shouldDispatchWebhook($action)) {
            DispatchWebhookEventJob::dispatch(
                event: $auditLog->action,
                organizationId: $auditLog->organization_id,
                actorId: $auditLog->actor_id,
                targetType: $auditLog->target_type,
                targetId: $auditLog->target_id,
                metadata: $auditLog->metadata,
                occurredAt: $auditLog->occurred_at->toIso8601String(),
            );
        }

        return $auditLog;
    }

    private function shouldDispatchWebhook(string $action): bool
    {
        if (! config('approvehub.webhooks.enabled', false)) {
            return false;
        }

        /** @var array<int, string> $events */
        $events = config('approvehub.webhooks.events', []);

        if ($events === []) {
            return false;
        }

        return in_array($action, $events, true);
    }
}
