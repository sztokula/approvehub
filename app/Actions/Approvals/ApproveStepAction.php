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
use App\Notifications\ReviewStepActivatedNotification;
use DomainException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Handles ApproveStepAction responsibilities for the ApproveHub domain.
 */
class ApproveStepAction
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
                throw new DomainException('Only active steps can be approved.');
            }

            if (! $this->canActOnStep($step, $actor)) {
                throw new DomainException('User is not allowed to approve this step.');
            }

            $decision = $step->decisions()->create([
                'actor_id' => $actor->id,
                'decision' => ApprovalDecisionStatus::Approved,
                'note' => $note,
            ]);

            $step->update([
                'status' => ApprovalStepStatus::Approved,
                'decided_at' => now(),
                'decision_note' => $note,
            ]);

            $workflow = $step->workflow;
            $nextStep = $workflow->steps()
                ->where('step_order', '>', $step->step_order)
                ->orderBy('step_order')
                ->first();

            if ($nextStep !== null) {
                $nextStep->update([
                    'status' => ApprovalStepStatus::Active,
                    'activated_at' => now(),
                ]);

                $this->recordAuditLogAction->execute(
                    organizationId: $workflow->documentVersion->document->organization_id,
                    actor: $actor,
                    action: 'approval.step.activated',
                    targetType: 'approval_step',
                    targetId: $nextStep->id,
                    metadata: [
                        'workflow_id' => $workflow->id,
                        'step_order' => $nextStep->step_order,
                    ],
                );

                $this->resolveRecipientsForStep($nextStep)->each(
                    fn (User $recipient) => $recipient->notify(
                        new ReviewStepActivatedNotification($workflow->documentVersion->document, $nextStep)
                    )
                );
            } else {
                $workflow->update([
                    'status' => WorkflowStatus::Approved,
                    'completed_at' => now(),
                ]);

                $workflow->documentVersion->document->update([
                    'status' => DocumentStatus::Approved,
                ]);
            }

            $document = $workflow->documentVersion->document;

            $this->recordAuditLogAction->execute(
                organizationId: $document->organization_id,
                actor: $actor,
                action: 'approval.approved',
                targetType: 'approval_step',
                targetId: $step->id,
                metadata: [
                    'workflow_id' => $workflow->id,
                    'decision_id' => $decision->id,
                ],
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

    /**
     * @return Collection<int, User>
     */
    private function resolveRecipientsForStep(ApprovalStep $step): Collection
    {
        $step->loadMissing(['assignees.user', 'workflow.documentVersion.document', 'fallbackUser']);

        $assigneeUsers = $step->assignees->pluck('user')->filter();

        if ($assigneeUsers->isNotEmpty()) {
            return User::query()->whereKey($assigneeUsers->pluck('id'))->get();
        }

        if ($step->assignee_role !== null) {
            $organizationId = $step->workflow->documentVersion->document->organization_id;

            $roleRecipients = User::query()
                ->whereHas('organizationMemberships', function ($query) use ($organizationId, $step): void {
                    $query->where('organization_id', $organizationId)
                        ->whereHas('role', fn ($role) => $role->where('name', $step->assignee_role));
                })
                ->get();

            if ($roleRecipients->isNotEmpty()) {
                return $roleRecipients;
            }
        }

        if ($step->fallbackUser !== null) {
            return User::query()->whereKey($step->fallbackUser->id)->get();
        }

        return collect();
    }
}
