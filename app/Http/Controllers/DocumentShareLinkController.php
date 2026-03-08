<?php

namespace App\Http\Controllers;

use App\Actions\PublicShareLinks\CreatePublicShareLinkAction;
use App\Actions\PublicShareLinks\RevokePublicShareLinkAction;
use App\Http\Requests\StorePublicShareLinkRequest;
use App\Models\Document;
use App\Models\PublicShareLink;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;

/**
 * Handles DocumentShareLinkController responsibilities for the ApproveHub domain.
 */
class DocumentShareLinkController extends Controller
{
    public function store(
        StorePublicShareLinkRequest $request,
        Document $document,
        CreatePublicShareLinkAction $createPublicShareLinkAction,
    ): JsonResponse|RedirectResponse {
        $this->authorize('manageShareLinks', $document);

        $publicShareLink = $createPublicShareLinkAction->execute(
            document: $document,
            actor: $request->user(),
            expiresAt: $request->validated('expires_at'),
        );

        if (! $request->expectsJson()) {
            return redirect()
                ->route('documents.show', $document)
                ->with('status', 'Public share link created.');
        }

        return response()->json($publicShareLink, 201);
    }

    public function destroy(
        Document $document,
        PublicShareLink $publicShareLink,
        RevokePublicShareLinkAction $revokePublicShareLinkAction,
    ): JsonResponse|RedirectResponse {
        abort_if($publicShareLink->document_id !== $document->id, 404);
        $this->authorize('delete', $publicShareLink);

        $revokePublicShareLinkAction->execute($publicShareLink, request()->user());

        if (! request()->expectsJson()) {
            return redirect()
                ->route('documents.show', $document)
                ->with('status', 'Public share link revoked.');
        }

        return response()->json([
            'message' => 'Public share link revoked.',
        ]);
    }
}
