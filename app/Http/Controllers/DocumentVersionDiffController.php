<?php

namespace App\Http\Controllers;

use App\Actions\Documents\BuildVersionDiffAction;
use App\Http\Requests\CompareDocumentVersionsRequest;
use App\Models\Document;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * Handles DocumentVersionDiffController responsibilities for the ApproveHub domain.
 */
class DocumentVersionDiffController extends Controller
{
    public function show(
        CompareDocumentVersionsRequest $request,
        Document $document,
        BuildVersionDiffAction $buildVersionDiffAction,
    ): JsonResponse|View {
        $this->authorize('view', $document);

        $document->load('versions.creator:id,name');

        $fromVersion = $document->versions->firstWhere('id', (int) $request->validated('from_version_id'));
        $toVersion = $document->versions->firstWhere('id', (int) $request->validated('to_version_id'));

        if ($fromVersion === null || $toVersion === null) {
            throw new UnprocessableEntityHttpException('Selected versions do not belong to this document.');
        }

        $diff = $buildVersionDiffAction->execute($fromVersion, $toVersion);

        if ($request->expectsJson()) {
            return response()->json([
                'document' => $document,
                'diff' => $diff,
            ]);
        }

        return view('documents.diff', [
            'document' => $document,
            'fromVersion' => $fromVersion,
            'toVersion' => $toVersion,
            'diff' => $diff,
        ]);
    }
}
