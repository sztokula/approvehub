<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Document;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Handles DocumentAuditExportController responsibilities for the ApproveHub domain.
 */
class DocumentAuditExportController extends Controller
{
    public function __invoke(Document $document, Request $request): JsonResponse|StreamedResponse
    {
        $this->authorize('view', $document);

        $format = strtolower($request->query('format', 'json'));

        $logs = AuditLog::query()
            ->with('actor:id,name,email')
            ->where('organization_id', $document->organization_id)
            ->where(function ($query) use ($document): void {
                $query->where(function ($direct) use ($document): void {
                    $direct->where('target_type', 'document')
                        ->where('target_id', $document->id);
                })->orWhereRaw("json_extract(metadata, '$.document_id') = ?", [$document->id]);
            })
            ->orderBy('occurred_at')
            ->get();

        if ($request->expectsJson() && $format !== 'csv') {
            return response()->json($logs->map(fn (AuditLog $log) => [
                'occurred_at' => optional($log->occurred_at)->toDateTimeString(),
                'action' => $log->action,
                'target_type' => $log->target_type,
                'target_id' => $log->target_id,
                'actor' => [
                    'id' => $log->actor?->id,
                    'name' => $log->actor?->name,
                    'email' => $log->actor?->email,
                ],
                'metadata' => $log->metadata,
            ])->values()->all());
        }

        if ($format === 'csv') {
            return response()->streamDownload(function () use ($logs): void {
                $stream = fopen('php://output', 'wb');
                fputcsv($stream, ['occurred_at', 'action', 'target_type', 'target_id', 'actor', 'metadata']);

                foreach ($logs as $log) {
                    fputcsv($stream, [
                        optional($log->occurred_at)->toDateTimeString(),
                        $log->action,
                        $log->target_type,
                        $log->target_id,
                        $log->actor?->email ?? $log->actor?->name ?? 'system',
                        json_encode($log->metadata, JSON_UNESCAPED_UNICODE),
                    ]);
                }

                fclose($stream);
            }, "document-{$document->id}-audit.csv");
        }

        return response()->streamDownload(function () use ($logs): void {
            echo json_encode($logs->map(fn (AuditLog $log) => [
                'occurred_at' => optional($log->occurred_at)->toDateTimeString(),
                'action' => $log->action,
                'target_type' => $log->target_type,
                'target_id' => $log->target_id,
                'actor' => [
                    'id' => $log->actor?->id,
                    'name' => $log->actor?->name,
                    'email' => $log->actor?->email,
                ],
                'metadata' => $log->metadata,
            ])->values()->all(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        }, "document-{$document->id}-audit.json");
    }
}
