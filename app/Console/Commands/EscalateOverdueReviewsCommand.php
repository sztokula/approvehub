<?php

namespace App\Console\Commands;

use App\Actions\Audit\RecordAuditLogAction;
use App\Enums\ApprovalStepStatus;
use App\Enums\UserRole;
use App\Models\ApprovalStep;
use App\Models\AuditLog;
use App\Models\User;
use App\Notifications\ReviewEscalatedNotification;
use Illuminate\Console\Command;

/**
 * Handles EscalateOverdueReviewsCommand responsibilities for the ApproveHub domain.
 */
class EscalateOverdueReviewsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'review:escalate-overdue {--hours=24 : Escalate if step is overdue at least N hours}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Escalate overdue active review steps to organization admins.';

    /**
     * Execute the console command.
     */
    public function handle(RecordAuditLogAction $recordAuditLogAction): int
    {
        $hours = max(1, (int) $this->option('hours'));
        $overdueBefore = now()->subHours($hours);

        $steps = ApprovalStep::query()
            ->with(['workflow.documentVersion.document', 'assignees.user'])
            ->where('status', ApprovalStepStatus::Active)
            ->whereNotNull('due_at')
            ->where('due_at', '<=', $overdueBefore)
            ->get();

        $escalatedCount = 0;

        foreach ($steps as $step) {
            if ($this->wasEscalatedRecently($step->id)) {
                continue;
            }

            $document = $step->workflow->documentVersion->document;

            $admins = User::query()
                ->whereHas('organizationMemberships', function ($query) use ($document): void {
                    $query->where('organization_id', $document->organization_id)
                        ->whereHas('role', fn ($role) => $role->where('name', UserRole::Admin->value));
                })
                ->get();

            if ($admins->isEmpty()) {
                continue;
            }

            $admins->each(
                fn (User $admin) => $admin->notify(new ReviewEscalatedNotification($document, $step))
            );

            $recordAuditLogAction->execute(
                organizationId: $document->organization_id,
                actor: null,
                action: 'review.escalated',
                targetType: 'approval_step',
                targetId: $step->id,
                metadata: [
                    'document_id' => $document->id,
                    'admin_ids' => $admins->pluck('id')->all(),
                    'due_at' => optional($step->due_at)->toDateTimeString(),
                ],
            );

            $escalatedCount++;
        }

        $this->info("Escalated {$escalatedCount} overdue step(s).");

        return self::SUCCESS;
    }

    private function wasEscalatedRecently(int $stepId): bool
    {
        return AuditLog::query()
            ->where('action', 'review.escalated')
            ->where('target_type', 'approval_step')
            ->where('target_id', $stepId)
            ->where('occurred_at', '>=', now()->subHours(12))
            ->exists();
    }
}
