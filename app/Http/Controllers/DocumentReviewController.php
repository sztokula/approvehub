<?php

namespace App\Http\Controllers;

use App\Actions\Documents\BuildReviewStepsFromTemplateAction;
use App\Actions\Documents\SubmitDocumentForReviewAction;
use App\Http\Requests\SubmitDocumentReviewRequest;
use App\Models\Document;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * Handles DocumentReviewController responsibilities for the ApproveHub domain.
 */
class DocumentReviewController extends Controller
{
    public function store(
        SubmitDocumentReviewRequest $request,
        Document $document,
        BuildReviewStepsFromTemplateAction $buildReviewStepsFromTemplateAction,
        SubmitDocumentForReviewAction $submitDocumentForReviewAction,
    ): JsonResponse|RedirectResponse {
        $version = $document->currentVersion;

        if ($version === null) {
            throw new UnprocessableEntityHttpException('Document has no current version.');
        }

        $steps = $request->validated('steps', []);

        if ($request->filled('template_id')) {
            $steps = $buildReviewStepsFromTemplateAction->execute(
                document: $document,
                templateId: (int) $request->validated('template_id'),
            );
        }

        $submitDocumentForReviewAction->execute(
            version: $version,
            actor: $request->user(),
            steps: $steps,
        );

        if (! $request->expectsJson()) {
            return redirect()
                ->route('documents.show', $document)
                ->with('status', 'Document submitted for review.');
        }

        return response()->json([
            'message' => 'Document submitted for review.',
        ]);
    }
}
