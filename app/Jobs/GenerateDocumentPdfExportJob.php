<?php

namespace App\Jobs;

use App\Actions\Documents\GenerateDocumentPdfAction;
use App\Models\Document;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Handles GenerateDocumentPdfExportJob responsibilities for the ApproveHub domain.
 */
class GenerateDocumentPdfExportJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly int $documentId,
        public readonly int $requestedBy,
        public readonly string $token,
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $cacheKey = $this->cacheKey();

        try {
            $document = Document::query()->findOrFail($this->documentId);
            $pdfBinary = app(GenerateDocumentPdfAction::class)->execute($document);

            $path = "exports/{$this->token}.pdf";
            Storage::disk('local')->put($path, $pdfBinary);

            Cache::put($cacheKey, [
                'status' => 'ready',
                'requested_by' => $this->requestedBy,
                'document_id' => $this->documentId,
                'path' => $path,
                'file_name' => "document-{$this->documentId}.pdf",
            ], now()->addDay());
        } catch (Throwable $exception) {
            Cache::put($cacheKey, [
                'status' => 'failed',
                'requested_by' => $this->requestedBy,
                'document_id' => $this->documentId,
                'error' => $exception->getMessage(),
            ], now()->addDay());

            report($exception);
        }
    }

    private function cacheKey(): string
    {
        return "document_pdf_export:{$this->token}";
    }
}
