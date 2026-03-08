<?php

namespace App\Actions\Documents;

use App\Models\DocumentVersion;

/**
 * Handles BuildVersionDiffAction responsibilities for the ApproveHub domain.
 */
class BuildVersionDiffAction
{
    /**
     * @return array{
     *     from: int,
     *     to: int,
     *     summary: array{added: int, removed: int, unchanged: int},
     *     lines: list<array{type: string, left: string|null, right: string|null}>,
     *     metadata: list<array{key: string, from: mixed, to: mixed, changed: bool}>
     * }
     */
    public function execute(DocumentVersion $fromVersion, DocumentVersion $toVersion): array
    {
        $leftLines = preg_split('/\R/u', (string) $fromVersion->content_snapshot) ?: [];
        $rightLines = preg_split('/\R/u', (string) $toVersion->content_snapshot) ?: [];
        $length = max(count($leftLines), count($rightLines));

        $lines = [];
        $added = 0;
        $removed = 0;
        $unchanged = 0;

        for ($index = 0; $index < $length; $index++) {
            $left = $leftLines[$index] ?? null;
            $right = $rightLines[$index] ?? null;

            if ($left === $right) {
                $lines[] = [
                    'type' => 'unchanged',
                    'left' => $left,
                    'right' => $right,
                ];
                $unchanged++;

                continue;
            }

            if ($left !== null) {
                $lines[] = [
                    'type' => 'removed',
                    'left' => $left,
                    'right' => null,
                ];
                $removed++;
            }

            if ($right !== null) {
                $lines[] = [
                    'type' => 'added',
                    'left' => null,
                    'right' => $right,
                ];
                $added++;
            }
        }

        $metadataDiff = $this->buildMetadataDiff(
            is_array($fromVersion->meta_snapshot) ? $fromVersion->meta_snapshot : [],
            is_array($toVersion->meta_snapshot) ? $toVersion->meta_snapshot : [],
        );

        return [
            'from' => $fromVersion->version_number,
            'to' => $toVersion->version_number,
            'summary' => [
                'added' => $added,
                'removed' => $removed,
                'unchanged' => $unchanged,
            ],
            'lines' => $lines,
            'metadata' => $metadataDiff,
        ];
    }

    /**
     * @param  array<string, mixed>  $fromMeta
     * @param  array<string, mixed>  $toMeta
     * @return list<array{key: string, from: mixed, to: mixed, changed: bool}>
     */
    private function buildMetadataDiff(array $fromMeta, array $toMeta): array
    {
        $allKeys = array_unique([...array_keys($fromMeta), ...array_keys($toMeta)]);
        sort($allKeys);

        $diff = [];

        foreach ($allKeys as $key) {
            $fromValue = $fromMeta[$key] ?? null;
            $toValue = $toMeta[$key] ?? null;

            $diff[] = [
                'key' => (string) $key,
                'from' => $fromValue,
                'to' => $toValue,
                'changed' => $fromValue !== $toValue,
            ];
        }

        return $diff;
    }
}
