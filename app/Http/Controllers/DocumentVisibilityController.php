<?php

namespace App\Http\Controllers;

use App\Actions\Documents\UpdateDocumentVisibilityAction;
use App\Enums\DocumentVisibility;
use App\Http\Requests\UpdateDocumentVisibilityRequest;
use App\Models\Document;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;

/**
 * Handles DocumentVisibilityController responsibilities for the ApproveHub domain.
 */
class DocumentVisibilityController extends Controller
{
    public function update(
        UpdateDocumentVisibilityRequest $request,
        Document $document,
        UpdateDocumentVisibilityAction $updateDocumentVisibilityAction,
    ): JsonResponse|RedirectResponse {
        $visibility = $request->enum('visibility', DocumentVisibility::class);

        if ($visibility !== null) {
            $updateDocumentVisibilityAction->execute($document, $request->user(), $visibility);
        }

        if (! $request->expectsJson()) {
            return redirect()
                ->route('documents.show', $document)
                ->with('status', 'Document visibility updated.');
        }

        return response()->json([
            'message' => 'Document visibility updated.',
        ]);
    }
}
