<?php

namespace App\Actions\Documents;

use App\Enums\ApprovalAssigneeType;
use App\Models\Document;
use App\Models\WorkflowTemplate;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * Handles BuildReviewStepsFromTemplateAction responsibilities for the ApproveHub domain.
 */
class BuildReviewStepsFromTemplateAction
{
    /**
     * @return array<int, array{name: string, assignee_type: string, assignee_role?: string|null, fallback_user_id?: int|null, assignees?: array<int, int>, due_at?: string|null}>
     */
    public function execute(Document $document, int $templateId): array
    {
        $template = WorkflowTemplate::query()
            ->with('steps')
            ->where('organization_id', $document->organization_id)
            ->findOrFail($templateId);

        if ($template->document_type !== $document->document_type && $template->document_type !== 'general') {
            throw new UnprocessableEntityHttpException('Template document type does not match document type.');
        }

        return $template->steps
            ->sortBy('step_order')
            ->values()
            ->map(function ($step): array {
                $assignees = [];

                if ($step->assignee_type === ApprovalAssigneeType::User && $step->assignee_user_id !== null) {
                    $assignees[] = $step->assignee_user_id;
                }

                return [
                    'name' => $step->name,
                    'assignee_type' => $step->assignee_type->value,
                    'assignee_role' => $step->assignee_role,
                    'fallback_user_id' => $step->fallback_user_id,
                    'assignees' => $assignees,
                    'due_at' => $step->due_in_hours !== null
                        ? now()->addHours($step->due_in_hours)->toDateTimeString()
                        : null,
                ];
            })
            ->all();
    }
}
