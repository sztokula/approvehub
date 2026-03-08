<?php

namespace App\Http\Controllers;

use App\Actions\Documents\ArchiveDocumentAction;
use App\Http\Requests\ArchiveDocumentRequest;
use App\Models\Document;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;

/**
 * Handles DocumentArchiveController responsibilities for the ApproveHub domain.
 */
class DocumentArchiveController extends Controller
{
    public function store(
        ArchiveDocumentRequest $request,
        Document $document,
        ArchiveDocumentAction $archiveDocumentAction,
    ): JsonResponse|RedirectResponse {
        try {
            $archiveDocumentAction->execute($document, $request->user());
        } catch (DomainException $exception) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => $exception->getMessage(),
                ], 422);
            }

            return redirect()
                ->route('documents.show', $document)
                ->withErrors(['document' => $exception->getMessage()]);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Document archived.',
            ]);
        }

        return redirect()
            ->route('documents.show', $document)
            ->with('status', 'Document archived.');
    }
}
