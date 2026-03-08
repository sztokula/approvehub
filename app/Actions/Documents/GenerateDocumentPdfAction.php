<?php

namespace App\Actions\Documents;

use App\Models\AuditLog;
use App\Models\Document;
use App\Models\DocumentVersion;

/**
 * Handles GenerateDocumentPdfAction responsibilities for the ApproveHub domain.
 */
class GenerateDocumentPdfAction
{
    public function execute(Document $document): string
    {
        $document->loadMissing([
            'organization:id,name',
            'owner:id,name',
            'currentVersion:id,document_id,version_number,title_snapshot,content_snapshot,created_at',
            'versions:id,document_id,version_number,title_snapshot,created_by,created_at',
            'versions.creator:id,name',
            'comments:id,document_id,version_id,user_id,body,created_at',
            'comments.user:id,name',
        ]);

        $auditLogs = AuditLog::query()
            ->with('actor:id,name')
            ->where('organization_id', $document->organization_id)
            ->where(function ($query) use ($document): void {
                $query->where(function ($direct) use ($document): void {
                    $direct->where('target_type', 'document')
                        ->where('target_id', $document->id);
                })->orWhereRaw("json_extract(metadata, '$.document_id') = ?", [$document->id]);
            })
            ->latest('occurred_at')
            ->limit(40)
            ->get();

        $lines = $this->buildSections($document, $auditLogs);
        $pages = array_chunk($lines, 46);
        $objects = $this->buildPdfObjects($pages);

        return $this->buildPdf($objects);
    }

    /**
     * @param  \Illuminate\Support\Collection<int, AuditLog>  $auditLogs
     * @return list<string>
     */
    private function buildSections(Document $document, $auditLogs): array
    {
        $lines = [
            'ApproveHub Document Export',
            '',
            'Document Overview',
            "Document ID: {$document->id}",
            "Organization: {$document->organization->name}",
            "Title: {$document->title}",
            "Type: {$document->document_type}",
            "Status: {$document->status->value}",
            "Owner: {$document->owner->name}",
            'Exported At: '.now()->toDateTimeString(),
            '',
            'Current Version',
            'Version: v'.($document->currentVersion?->version_number ?? 0),
            'Title Snapshot: '.($document->currentVersion?->title_snapshot ?? 'N/A'),
        ];

        $lines = [...$lines, ...$this->wrapMultilineText((string) $document->currentVersion?->content_snapshot, 95, 24)];
        $lines[] = '';
        $lines[] = 'Versions';

        foreach ($document->versions->sortByDesc('version_number')->take(30) as $version) {
            $lines[] = sprintf(
                'v%s | %s | by %s | %s',
                $version->version_number,
                $version->title_snapshot,
                $version->creator?->name ?? 'Unknown',
                $version->created_at->toDateTimeString(),
            );
        }

        $lines[] = '';
        $lines[] = 'Comments';

        if ($document->comments->isEmpty()) {
            $lines[] = 'No comments recorded.';
        } else {
            foreach ($document->comments->sortByDesc('created_at')->take(40) as $comment) {
                $lines[] = sprintf(
                    '[%s] %s (v%s)',
                    $comment->created_at->toDateTimeString(),
                    $comment->user?->name ?? 'Unknown',
                    $this->resolveCommentVersionNumber($comment->version_id, $document->versions),
                );
                $lines = [...$lines, ...$this->wrapLine((string) $comment->body, 95, 3)];
            }
        }

        $lines[] = '';
        $lines[] = 'Audit Timeline';

        if ($auditLogs->isEmpty()) {
            $lines[] = 'No audit events found.';
        } else {
            foreach ($auditLogs as $log) {
                $lines[] = sprintf(
                    '[%s] %s by %s on %s#%s',
                    $log->occurred_at->toDateTimeString(),
                    $log->action,
                    $log->actor?->name ?? 'System',
                    $log->target_type,
                    $log->target_id,
                );
            }
        }

        return $lines;
    }

    /**
     * @param  array<int, array<int, string>>  $pages
     * @return array<int, string>
     */
    private function buildPdfObjects(array $pages): array
    {
        $objects = [
            1 => '<< /Type /Catalog /Pages 2 0 R >>',
            3 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
        ];

        $pageReferences = [];
        $nextObjectId = 4;
        $totalPages = count($pages);

        foreach ($pages as $index => $lines) {
            $contentObjectId = $nextObjectId++;
            $pageObjectId = $nextObjectId++;

            $streamBody = $this->buildPageStream($lines, $index + 1, $totalPages);
            $objects[$contentObjectId] = '<< /Length '.strlen($streamBody)." >>\nstream\n{$streamBody}\nendstream";
            $objects[$pageObjectId] = "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 3 0 R >> >> /Contents {$contentObjectId} 0 R >>";
            $pageReferences[] = "{$pageObjectId} 0 R";
        }

        $objects[2] = '<< /Type /Pages /Count '.count($pageReferences).' /Kids ['.implode(' ', $pageReferences).'] >>';

        return $objects;
    }

    /**
     * @param  list<string>  $lines
     */
    private function buildPageStream(array $lines, int $pageNumber, int $totalPages): string
    {
        $streamBody = "BT\n/F1 11 Tf\n50 800 Td\n14 TL\n";

        foreach ($lines as $line) {
            $escaped = $this->escapePdfText($line);
            $streamBody .= "({$escaped}) Tj\nT*\n";
        }

        $streamBody .= "T*\n(Page {$pageNumber}/{$totalPages}) Tj\nET";

        return $streamBody;
    }

    /**
     * @return list<string>
     */
    private function wrapMultilineText(string $text, int $lineLength, int $maxLines): array
    {
        $lines = preg_split('/\R/u', $text) ?: [];
        $wrapped = [];

        foreach ($lines as $line) {
            $wrapped = [...$wrapped, ...$this->wrapLine($line, $lineLength)];
            if (count($wrapped) >= $maxLines) {
                return array_slice($wrapped, 0, $maxLines);
            }
        }

        return $wrapped;
    }

    /**
     * @return list<string>
     */
    private function wrapLine(string $line, int $lineLength, int $maxLines = 100): array
    {
        $trimmed = trim($line);
        if ($trimmed === '') {
            return [''];
        }

        $words = preg_split('/\s+/u', $trimmed) ?: [];
        $wrapped = [];
        $currentLine = '';

        foreach ($words as $word) {
            $candidateLine = $currentLine === '' ? $word : "{$currentLine} {$word}";

            if (mb_strlen($candidateLine) <= $lineLength) {
                $currentLine = $candidateLine;

                continue;
            }

            if ($currentLine !== '') {
                $wrapped[] = $currentLine;
            }

            $currentLine = $word;

            if (count($wrapped) >= $maxLines) {
                return array_slice($wrapped, 0, $maxLines);
            }
        }

        if ($currentLine !== '' && count($wrapped) < $maxLines) {
            $wrapped[] = $currentLine;
        }

        return $wrapped;
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Collection<int, DocumentVersion>  $versions
     */
    private function resolveCommentVersionNumber(?int $versionId, $versions): string
    {
        if ($versionId === null) {
            return '-';
        }

        /** @var DocumentVersion|null $version */
        $version = $versions->firstWhere('id', $versionId);

        return $version?->version_number !== null ? (string) $version->version_number : '-';
    }

    private function escapePdfText(string $text): string
    {
        $converted = iconv('UTF-8', 'windows-1252//TRANSLIT//IGNORE', $text);
        $converted = $converted === false ? '' : $converted;

        return str_replace(
            ['\\', '(', ')'],
            ['\\\\', '\\(', '\\)'],
            $converted,
        );
    }

    /**
     * @param  array<int, string>  $objects
     */
    private function buildPdf(array $objects): string
    {
        $pdf = "%PDF-1.4\n";
        $offsets = [0];
        $objectIds = array_keys($objects);
        sort($objectIds, SORT_NUMERIC);

        foreach ($objectIds as $id) {
            $object = $objects[$id];
            $offsets[$id] = strlen($pdf);
            $pdf .= "{$id} 0 obj\n{$object}\nendobj\n";
        }

        $maxObjectId = (int) max($objectIds);
        $xrefOffset = strlen($pdf);
        $pdf .= "xref\n0 ".($maxObjectId + 1)."\n";
        $pdf .= "0000000000 65535 f \n";

        for ($id = 1; $id <= $maxObjectId; $id++) {
            if (! isset($offsets[$id])) {
                $pdf .= "0000000000 65535 f \n";

                continue;
            }

            $pdf .= str_pad((string) $offsets[$id], 10, '0', STR_PAD_LEFT)." 00000 n \n";
        }

        $pdf .= "trailer\n<< /Size ".($maxObjectId + 1)." /Root 1 0 R >>\n";
        $pdf .= "startxref\n{$xrefOffset}\n%%EOF";

        return $pdf;
    }
}
