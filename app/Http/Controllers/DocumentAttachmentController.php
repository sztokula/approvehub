<?php

namespace App\Http\Controllers;

use App\Actions\Attachments\DeleteAttachmentAction;
use App\Actions\Attachments\UploadAttachmentAction;
use App\Http\Requests\StoreAttachmentRequest;
use App\Models\Attachment;
use App\Models\Document;
use App\Models\DocumentVersion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Handles DocumentAttachmentController responsibilities for the ApproveHub domain.
 */
class DocumentAttachmentController extends Controller
{
    public function store(
        StoreAttachmentRequest $request,
        Document $document,
        UploadAttachmentAction $uploadAttachmentAction,
    ): JsonResponse|RedirectResponse {
        $version = null;

        if ($request->filled('version_id')) {
            $version = DocumentVersion::query()->findOrFail((int) $request->validated('version_id'));
        }

        $attachment = $uploadAttachmentAction->execute(
            document: $document,
            actor: $request->user(),
            file: $request->file('file'),
            version: $version,
        );

        if (! $request->expectsJson()) {
            return redirect()
                ->route('documents.show', $document)
                ->with('status', 'Attachment uploaded.');
        }

        return response()->json($attachment, 201);
    }

    public function destroy(
        Document $document,
        Attachment $attachment,
        DeleteAttachmentAction $deleteAttachmentAction,
    ): JsonResponse|RedirectResponse {
        abort_if($attachment->document_id !== $document->id, 404);
        $this->authorize('delete', $attachment);

        $deleteAttachmentAction->execute($attachment, request()->user());

        if (! request()->expectsJson()) {
            return redirect()
                ->route('documents.show', $document)
                ->with('status', 'Attachment deleted.');
        }

        return response()->json([
            'message' => 'Attachment deleted.',
        ]);
    }

    public function download(Document $document, Attachment $attachment): StreamedResponse
    {
        abort_if($attachment->document_id !== $document->id, 404);
        $this->authorize('view', $attachment);

        return Storage::disk($attachment->disk)->download($attachment->path, $attachment->original_name);
    }
}
