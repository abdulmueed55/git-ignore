<?php

namespace App\Services\Admissions;

/**
 * Used when no AI key is configured. Never invents values: documents are marked
 * "skipped" and staff / applicants type the details themselves.
 */
class ManualExtractor implements DocumentExtractor
{
    public function extract(string $type, string $binary, string $mime): array
    {
        return [
            'status' => 'skipped',
            'data' => null,
            'confidence' => null,
            'error' => 'AI reading is not configured — enter details manually.',
            'by' => 'manual',
        ];
    }
}
