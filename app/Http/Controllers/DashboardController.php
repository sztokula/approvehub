<?php

namespace App\Http\Controllers;

use App\Enums\ApprovalStepStatus;
use App\Enums\DocumentStatus;
use App\Models\ApprovalDecision;
use App\Models\ApprovalStep;
use App\Models\Document;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * Handles DashboardController responsibilities for the ApproveHub domain.
 */
class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $organizationIds = $request->user()->organizations()->pluck('organizations.id');

        $documentCounts = Document::query()
            ->whereIn('organization_id', $organizationIds)
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $pendingForMe = ApprovalStep::query()
            ->whereHas('workflow.documentVersion.document', fn ($query) => $query->whereIn('organization_id', $organizationIds))
            ->where('status', ApprovalStepStatus::Active)
            ->whereHas('assignees', fn ($assignees) => $assignees->where('user_id', $request->user()->id))
            ->with(['workflow.documentVersion.document:id,title'])
            ->orderBy('updated_at')
            ->limit(10)
            ->get();

        $activeDueStepsCount = ApprovalStep::query()
            ->whereHas('workflow.documentVersion.document', fn ($query) => $query->whereIn('organization_id', $organizationIds))
            ->where('status', ApprovalStepStatus::Active)
            ->whereNotNull('due_at')
            ->count();

        $overdueStepsCount = ApprovalStep::query()
            ->whereHas('workflow.documentVersion.document', fn ($query) => $query->whereIn('organization_id', $organizationIds))
            ->where('status', ApprovalStepStatus::Active)
            ->whereNotNull('due_at')
            ->where('due_at', '<', now())
            ->count();

        $reviewerThroughput = ApprovalDecision::query()
            ->with('actor:id,name')
            ->whereHas('step.workflow.documentVersion.document', fn ($query) => $query->whereIn('organization_id', $organizationIds))
            ->where('created_at', '>=', now()->subDays(30))
            ->selectRaw('actor_id, count(*) as total_decisions')
            ->groupBy('actor_id')
            ->orderByDesc('total_decisions')
            ->limit(5)
            ->get();

        return view('dashboard', [
            'draftCount' => (int) ($documentCounts[DocumentStatus::Draft->value] ?? 0),
            'inReviewCount' => (int) ($documentCounts[DocumentStatus::InReview->value] ?? 0),
            'approvedCount' => (int) ($documentCounts[DocumentStatus::Approved->value] ?? 0),
            'rejectedCount' => (int) ($documentCounts[DocumentStatus::Rejected->value] ?? 0),
            'pendingForMe' => $pendingForMe,
            'activeDueStepsCount' => $activeDueStepsCount,
            'overdueStepsCount' => $overdueStepsCount,
            'reviewerThroughput' => $reviewerThroughput,
        ]);
    }
}
