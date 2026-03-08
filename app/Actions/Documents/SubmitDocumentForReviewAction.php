<?php

namespace App\Actions\Documents;

use App\Actions\Audit\RecordAuditLogAction;
use App\Enums\ApprovalStepStatus;
use App\Enums\DocumentStatus;
use App\Enums\WorkflowStatus;
use App\Models\ApprovalStep;
use App\Models\DocumentVersion;
use App\Models\User;
use App\Notifications\ReviewSubmittedNotification;
use DomainException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Handles SubmitDocumentForReviewAction responsibilities for the ApproveHub domain.
 */
class SubmitDocumentForReviewAction
{
    public function __construct(
        private readonly RecordAuditLogAction $recordAuditLogAction,
    ) {}

    /**
     * @param  array<int, array{name: string, assignee_type: string, assignee_role?: string|null, fallback_user_id?: int|null, assignees?: array<int, int>, due_at?: string|null}>  $steps
     */
    public function execute(DocumentVersion $version, User $actor, array $steps): void
    {
        if ($steps === []) {
            throw new DomainException('At least one approval step is required.');
        }

        DB::transaction(function () use ($version, $actor, $steps): void {
            $document = $version->document()->lockForUpdate()->firstOrFail();
            $currentStatus = $document->status;

            if (! in_array($currentStatus, [DocumentStatus::Draft, DocumentStatus::Rejected], true)) {
                throw new DomainException('Document cannot be submitted for review from the current status.');
            }

            if ($version->workflow()->exists()) {
                throw new DomainException('This version already has an approval workflow. Create a new version first.');
            }

            $workflow = $version->workflow()->create([
                'status' => WorkflowStatus::InProgress,
                'submitted_by' => $actor->id,
                'submitted_at' => now(),
            ]);

            foreach ($steps as $index => $stepData) {
                $step = $workflow->steps()->create([
                    'step_order' => $index + 1,
                    'name' => $stepData['name'],
                    'assignee_type' => $stepData['assignee_type'],
                    'assignee_role' => $stepData['assignee_role'] ?? null,
                    'fallback_user_id' => $stepData['fallback_user_id'] ?? null,
                    'status' => $index === 0 ? ApprovalStepStatus::Active : ApprovalStepStatus::Pending,
                    'activated_at' => $index === 0 ? now() : null,
                    'due_at' => $stepData['due_at'] ?? null,
                ]);

                $this->attachAssignees($step, $stepData['assignees'] ?? []);
            }

            $firstStep = $workflow->steps()->where('step_order', 1)->first();

            $document->update([
                'status' => DocumentStatus::InReview,
            ]);

            $this->recordAuditLogAction->execute(
                organizationId: $document->organization_id,
                actor: $actor,
                action: 'review.submitted',
                targetType: 'approval_workflow',
                targetId: $workflow->id,
                metadata: [
                    'document_id' => $document->id,
                    'document_version_id' => $version->id,
                ],
            );

            $this->recordAuditLogAction->execute(
                organizationId: $document->organization_id,
                actor: $actor,
                action: 'workflow.started',
                targetType: 'approval_workflow',
                targetId: $workflow->id,
                metadata: [
                    'document_id' => $document->id,
                    'document_version_id' => $version->id,
                ],
            );

            if ($firstStep !== null) {
                $this->recordAuditLogAction->execute(
                    organizationId: $document->organization_id,
                    actor: $actor,
                    action: 'approval.step.activated',
                    targetType: 'approval_step',
                    targetId: $firstStep->id,
                    metadata: [
                        'workflow_id' => $workflow->id,
                        'step_order' => $firstStep->step_order,
                    ],
                );
            }

            if ($firstStep !== null) {
                $this->resolveRecipientsForStep($firstStep)->each(
                    fn (User $recipient) => $recipient->notify(new ReviewSubmittedNotification($document))
                );
            }
        });
    }

    /**
     * @param  array<int, int>  $assignees
     */
    private function attachAssignees(ApprovalStep $step, array $assignees): void
    {
        foreach ($assignees as $assigneeId) {
            $step->assignees()->create([
                'user_id' => $assigneeId,
                'is_required' => true,
            ]);
        }
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
