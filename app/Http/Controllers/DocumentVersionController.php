<?php

namespace App\Http\Controllers;

use App\Actions\Documents\CreateDocumentVersionAction;
use App\Http\Requests\StoreDocumentVersionRequest;
use App\Models\Document;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;

/**
 * Handles DocumentVersionController responsibilities for the ApproveHub domain.
 */
class DocumentVersionController extends Controller
{
    public function store(
        StoreDocumentVersionRequest $request,
        Document $document,
        CreateDocumentVersionAction $createDocumentVersionAction,
    ): JsonResponse|RedirectResponse {
        $version = $createDocumentVersionAction->execute(
            document: $document,
            actor: $request->user(),
            titleSnapshot: $request->validated('title_snapshot'),
            contentSnapshot: $request->validated('content_snapshot'),
            metaSnapshot: $request->validated('meta_snapshot'),
        );

        if (! $request->expectsJson()) {
            return redirect()
                ->route('documents.show', $document)
                ->with('status', 'New document version created.');
        }

        return response()->json($version->fresh(), 201);
    }
}
