<?php

namespace App\Actions\Approvals;

use App\Actions\Audit\RecordAuditLogAction;
use App\Enums\ApprovalDecisionStatus;
use App\Enums\ApprovalStepStatus;
use App\Enums\DocumentStatus;
use App\Enums\UserRole;
use App\Enums\WorkflowStatus;
use App\Models\ApprovalStep;
use App\Models\User;
use App\Notifications\ReviewRejectedNotification;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Handles RejectStepAction responsibilities for the ApproveHub domain.
 */
class RejectStepAction
{
    public function __construct(
        private readonly RecordAuditLogAction $recordAuditLogAction,
    ) {}

    public function execute(ApprovalStep $step, User $actor, ?string $note = null): void
    {
        DB::transaction(function () use ($step, $actor, $note): void {
            $step = ApprovalStep::query()
                ->with(['workflow.documentVersion.document', 'assignees'])
                ->lockForUpdate()
                ->findOrFail($step->id);

            if ($step->status !== ApprovalStepStatus::Active) {
                throw new DomainException('Only active steps can be rejected.');
            }

            if (! $this->canActOnStep($step, $actor)) {
                throw new DomainException('User is not allowed to reject this step.');
            }

            $decision = $step->decisions()->create([
                'actor_id' => $actor->id,
                'decision' => ApprovalDecisionStatus::Rejected,
                'note' => $note,
            ]);

            $step->update([
                'status' => ApprovalStepStatus::Rejected,
                'decided_at' => now(),
                'decision_note' => $note,
            ]);

            $workflow = $step->workflow;
            $workflow->update([
                'status' => WorkflowStatus::Rejected,
                'completed_at' => now(),
            ]);

            $document = $workflow->documentVersion->document;
            $document->update([
                'status' => DocumentStatus::Rejected,
            ]);

            $this->recordAuditLogAction->execute(
                organizationId: $document->organization_id,
                actor: $actor,
                action: 'approval.rejected',
                targetType: 'approval_step',
                targetId: $step->id,
                metadata: [
                    'workflow_id' => $workflow->id,
                    'decision_id' => $decision->id,
                ],
            );

            $recipients = collect([$document->owner, $workflow->submitter])
                ->filter()
                ->unique('id');

            $recipients->each(
                fn (User $recipient) => $recipient->notify(new ReviewRejectedNotification($document, $step, $note))
            );
        });
    }

    private function canActOnStep(ApprovalStep $step, User $actor): bool
    {
        if ($step->assignees()->where('user_id', $actor->id)->exists()) {
            return true;
        }

        if ($step->fallback_user_id === $actor->id) {
            return true;
        }

        if ($step->assignee_role === null) {
            return false;
        }

        $role = UserRole::tryFrom($step->assignee_role);

        if ($role === null) {
            return false;
        }

        $organizationId = $step->workflow->documentVersion->document->organization_id;

        return $actor->hasOrganizationRole($organizationId, $role);
    }
}
