<?php

namespace App\Http\Controllers;

use App\Jobs\GenerateDocumentPdfExportJob;
use App\Models\Document;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Handles DocumentPdfAsyncExportController responsibilities for the ApproveHub domain.
 */
class DocumentPdfAsyncExportController extends Controller
{
    public function store(Document $document, Request $request): JsonResponse
    {
        $this->authorize('view', $document);

        $token = Str::uuid()->toString();
        $cacheKey = $this->cacheKey($token);

        Cache::put($cacheKey, [
            'status' => 'pending',
            'requested_by' => $request->user()->id,
            'document_id' => $document->id,
        ], now()->addDay());

        GenerateDocumentPdfExportJob::dispatch(
            documentId: $document->id,
            requestedBy: $request->user()->id,
            token: $token,
        );

        return response()->json([
            'token' => $token,
            'status' => 'pending',
            'poll_url' => route('documents.pdf.exports.show', ['token' => $token]),
        ], Response::HTTP_ACCEPTED);
    }

    public function show(string $token, Request $request): JsonResponse|StreamedResponse
    {
        $cacheKey = $this->cacheKey($token);
        $state = Cache::get($cacheKey);

        if (! is_array($state)) {
            abort(404);
        }

        if (($state['requested_by'] ?? null) !== $request->user()->id) {
            abort(403);
        }

        if (($state['status'] ?? '') !== 'ready') {
            return response()->json($state, Response::HTTP_ACCEPTED);
        }

        $path = (string) ($state['path'] ?? '');
        if ($path === '' || ! Storage::disk('local')->exists($path)) {
            return response()->json([
                'status' => 'failed',
                'error' => 'Export file is unavailable.',
            ], 500);
        }

        return Storage::disk('local')->download($path, (string) ($state['file_name'] ?? 'document.pdf'));
    }

    private function cacheKey(string $token): string
    {
        return "document_pdf_export:{$token}";
    }
}
