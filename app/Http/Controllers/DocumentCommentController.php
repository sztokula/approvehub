<?php

namespace App\Http\Controllers;

use App\Actions\Comments\AddCommentAction;
use App\Http\Requests\StoreCommentRequest;
use App\Models\Document;
use App\Models\DocumentVersion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;

/**
 * Handles DocumentCommentController responsibilities for the ApproveHub domain.
 */
class DocumentCommentController extends Controller
{
    public function store(
        StoreCommentRequest $request,
        Document $document,
        AddCommentAction $addCommentAction,
    ): JsonResponse|RedirectResponse {
        $version = null;

        if ($request->filled('version_id')) {
            $version = DocumentVersion::query()->findOrFail((int) $request->validated('version_id'));
        }

        $comment = $addCommentAction->execute(
            document: $document,
            actor: $request->user(),
            body: $request->validated('body'),
            version: $version,
        );

        if (! $request->expectsJson()) {
            return redirect()
                ->route('documents.show', $document)
                ->with('status', 'Comment added.');
        }

        return response()->json($comment->load('user:id,name'), 201);
    }
}
