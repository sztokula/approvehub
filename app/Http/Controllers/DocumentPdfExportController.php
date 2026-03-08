<?php

namespace App\Http\Controllers;

use App\Actions\Documents\GenerateDocumentPdfAction;
use App\Models\Document;
use Symfony\Component\HttpFoundation\Response;

/**
 * Handles DocumentPdfExportController responsibilities for the ApproveHub domain.
 */
class DocumentPdfExportController extends Controller
{
    public function __invoke(Document $document, GenerateDocumentPdfAction $generateDocumentPdfAction): Response
    {
        $this->authorize('view', $document);

        $pdfBinary = $generateDocumentPdfAction->execute($document);
        $fileName = "document-{$document->id}.pdf";

        return response($pdfBinary, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$fileName.'"',
            'Content-Length' => strlen($pdfBinary),
        ]);
    }
}
