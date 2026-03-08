<?php

namespace App\Http\Controllers;

use App\Actions\Documents\RestoreDocumentVersionAction;
use App\Models\Document;
use App\Models\DocumentVersion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;

/**
 * Handles DocumentVersionRestoreController responsibilities for the ApproveHub domain.
 */
class DocumentVersionRestoreController extends Controller
{
    public function store(
        Document $document,
        DocumentVersion $version,
        RestoreDocumentVersionAction $restoreDocumentVersionAction,
    ): JsonResponse|RedirectResponse {
        $this->authorize('update', $document);
        abort_if($version->document_id !== $document->id, 404);

        $restoredVersion = $restoreDocumentVersionAction->execute($document, $version, request()->user());

        if (request()->expectsJson()) {
            return response()->json($restoredVersion, 201);
        }

        return redirect()
            ->route('documents.show', $document)
            ->with('status', 'Version restored as a new snapshot.');
    }
}
