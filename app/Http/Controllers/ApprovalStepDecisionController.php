<?php

namespace App\Http\Controllers;

use App\Actions\Approvals\ApproveStepAction;
use App\Actions\Approvals\RejectStepAction;
use App\Http\Requests\DecideApprovalStepRequest;
use App\Models\ApprovalStep;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;

/**
 * Handles ApprovalStepDecisionController responsibilities for the ApproveHub domain.
 */
class ApprovalStepDecisionController extends Controller
{
    public function approve(
        DecideApprovalStepRequest $request,
        ApprovalStep $step,
        ApproveStepAction $approveStepAction,
    ): JsonResponse|RedirectResponse {
        $approveStepAction->execute($step, $request->user(), $request->validated('note'));

        if (! $request->expectsJson()) {
            return redirect()
                ->route('documents.show', $step->workflow->documentVersion->document)
                ->with('status', 'Step approved.');
        }

        return response()->json([
            'message' => 'Step approved.',
        ]);
    }

    public function reject(
        DecideApprovalStepRequest $request,
        ApprovalStep $step,
        RejectStepAction $rejectStepAction,
    ): JsonResponse|RedirectResponse {
        $rejectStepAction->execute($step, $request->user(), $request->validated('note'));

        if (! $request->expectsJson()) {
            return redirect()
                ->route('documents.show', $step->workflow->documentVersion->document)
                ->with('status', 'Step rejected.');
        }

        return response()->json([
            'message' => 'Step rejected.',
        ]);
    }
}
