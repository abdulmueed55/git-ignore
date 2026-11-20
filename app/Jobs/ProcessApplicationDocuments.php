<?php

namespace App\Jobs;

use App\Models\AdmissionApplication;
use App\Services\Admissions\DocumentProcessor;
use Illuminate\Foundation\Bus\Dispatchable;

/**
 * Reads uploaded documents with AI, auto-fills, verifies and scores.
 * Dispatched with ::dispatchAfterResponse() so it runs right after the page
 * is sent — works on shared hosting without a queue worker.
 */
class ProcessApplicationDocuments
{
    use Dispatchable;

    public function __construct(public int $applicationId, public bool $force = false) {}

    public function handle(DocumentProcessor $processor): void
    {
        if ($app = AdmissionApplication::find($this->applicationId)) {
            $processor->process($app, $this->force);
        }
    }
}
