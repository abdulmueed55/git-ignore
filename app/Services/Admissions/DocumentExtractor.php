<?php

namespace App\Services\Admissions;

interface DocumentExtractor
{
    /**
     * Read one applicant document and return structured fields.
     *
     * @return array{status: 'done'|'failed'|'skipped', data: array<string, mixed>|null, confidence: int|null, error: string|null, by: string}
     */
    public function extract(string $type, string $binary, string $mime): array;
}
