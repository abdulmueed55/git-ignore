<?php

namespace App\Services\Admissions;

use App\Models\AdmissionApplication;
use App\Models\ApplicationDocument;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Throwable;

/** Reads pending documents → auto-fills the form → cross-verifies → recalculates merit. */
class DocumentProcessor
{
    public function __construct(
        private readonly AutoFiller $filler = new AutoFiller,
        private readonly CrossDocumentVerifier $verifier = new CrossDocumentVerifier,
        private readonly MeritCalculator $merit = new MeritCalculator,
    ) {}

    public function process(AdmissionApplication $app, bool $force = false): AdmissionApplication
    {
        $extractor = Extractors::resolve();
        $app->load('documents');

        foreach ($app->documents as $doc) {
            if (! $doc->isExtractable()) {
                if ($doc->extraction_status === 'pending') {
                    $doc->update(['extraction_status' => 'skipped', 'extraction_error' => null]);
                }

                continue;
            }
            if (! $force && ! in_array($doc->extraction_status, ['pending', 'processing'], true)) {
                continue;
            }
            $this->read($doc, $extractor);
        }

        $app->load('documents');
        $this->filler->fill($app);
        $this->verifier->verify($app->fresh(['documents', 'program']));
        $fresh = $app->fresh(['documents', 'program']);
        $this->merit->apply($fresh);

        return $fresh;
    }

    /** Caps AI document readings per day so a flood of uploads cannot run up the bill. */
    public static function withinDailyBudget(): bool
    {
        $limit = (int) config('services.anthropic.daily_limit', 600);
        if ($limit <= 0) {
            return true;
        }
        $key = 'ai-doc-reads:'.now()->format('Y-m-d');
        Cache::add($key, 0, now()->endOfDay());

        return Cache::increment($key) <= $limit;
    }

    public function read(ApplicationDocument $doc, ?DocumentExtractor $extractor = null): ApplicationDocument
    {
        $extractor ??= Extractors::resolve();
        $doc->update(['extraction_status' => 'processing']);
        try {
            $binary = Storage::disk('local')->get($doc->path);
            if ($binary === null) {
                throw new \RuntimeException('File is missing from storage.');
            }
            if (! ($extractor instanceof ManualExtractor) && ! self::withinDailyBudget()) {
                $r = (new ManualExtractor)->extract($doc->type, $binary, $doc->mime);
                $r['error'] = 'Automatic reading is paused for today (daily limit reached) — staff will check this document by hand.';
            } else {
                $r = $extractor->extract($doc->type, $binary, $doc->mime);
            }
        } catch (Throwable $e) {
            report($e); // details go to the log only, never to the applicant
            $r = ['status' => 'failed', 'data' => null, 'confidence' => null, 'error' => 'This file could not be read. Upload a clearer photo or scan.', 'by' => 'system'];
        }
        $doc->update([
            'extraction_status' => $r['status'],
            'extracted' => $r['data'],
            'confidence' => $r['confidence'],
            'extraction_error' => $r['error'] ? mb_substr($r['error'], 0, 250) : null,
            'extracted_by' => mb_substr($r['by'], 0, 32),
            'extracted_at' => now(),
        ]);

        return $doc;
    }
}
