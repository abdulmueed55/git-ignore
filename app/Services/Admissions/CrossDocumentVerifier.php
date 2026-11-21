<?php

namespace App\Services\Admissions;

use App\Models\AdmissionApplication;
use Carbon\Carbon;

/**
 * Compares the application form against every uploaded document, field by field,
 * plus integrity rules (duplicate CNIC, marks sanity, age, unreadable uploads).
 * Writes verification_checks and sets verification_score / verification_status.
 */
class CrossDocumentVerifier
{
    public const PARTIAL_AT = 80;

    public const MATCH_AT = 95;

    public function verify(AdmissionApplication $app): AdmissionApplication
    {
        $app->loadMissing(['documents', 'program']);
        $overrides = $app->checks()->where('overridden', true)->get()->keyBy('field');
        $rows = [];

        $docs = $app->documents->filter(fn ($d) => $d->extraction_status === 'done')->keyBy('type');
        $needsInter = (bool) $app->program?->basedOnInter() || $docs->has('inter_dmc');

        foreach (FieldMap::FIELDS as $field => [$label, $kind, $sources, $weight]) {
            if (in_array($field, FieldMap::INTER_FIELDS, true) && ! $needsInter) {
                continue;
            }
            if ($weight === 0) {
                continue;
            }
            $form = $app->{$field};
            $formStr = $form instanceof \DateTimeInterface ? $form->format('Y-m-d') : (is_bool($form) ? ($form ? '1' : '0') : (string) ($form ?? ''));

            $docValues = [];
            $scores = [];
            foreach ($sources as $docType => $key) {
                $doc = $docs->get($docType);
                if (! $doc) {
                    continue;
                }
                $val = $doc->extracted[$key] ?? null;
                if (blank($val)) {
                    continue;
                }
                $docValues[$docType] = (string) $val;
                $scores[$docType] = self::compare($kind, $formStr, (string) $val);
            }

            if ($formStr === '' && ! $docValues) {
                continue; // nothing to compare on either side
            }

            if (! $docValues) {
                $uploaded = $app->documents->whereIn('type', array_keys($sources));
                if ($uploaded->isNotEmpty()) {
                    continue; // uploaded but not machine-read: one document-level row covers it below
                }
                $rows[] = $this->row($field, $label, $formStr, [], 'missing', 0, 'No document to confirm this value yet.', $weight);

                continue;
            }

            if ($formStr === '') {
                $rows[] = $this->row($field, $label, null, $docValues, 'missing', 0, 'Form value empty — documents show a value.', $weight);

                continue;
            }

            $min = min($scores);
            $result = $min >= self::MATCH_AT ? 'match' : ($min >= self::PARTIAL_AT ? 'partial' : 'mismatch');
            $note = null;
            if ($result !== 'match') {
                $bad = array_keys(array_filter($scores, fn ($s) => $s < self::MATCH_AT));
                $labels = array_map(fn ($t) => AdmissionApplication::DOCUMENT_TYPES[$t] ?? $t, $bad);
                $note = ($result === 'partial' ? 'Spelling differs on ' : 'Does not match ').implode(', ', $labels).'.';
            }
            $rows[] = $this->row($field, $label, $formStr, $docValues, $result, $min, $note, $weight);
        }

        // ---------- Integrity rules ----------
        if (filled($app->cnic)) {
            $digits = Normalizer::digits($app->cnic);
            $dupes = AdmissionApplication::where('id', '!=', $app->id)
                ->where('admission_cycle_id', $app->admission_cycle_id)
                ->whereNotNull('cnic')->get(['id', 'cnic', 'tracking_no'])
                ->filter(fn ($o) => Normalizer::digits($o->cnic) === $digits);
            $rows[] = $dupes->isEmpty()
                ? $this->row('duplicate_cnic', 'Duplicate applicant check', $app->cnic, [], 'match', 100, 'No other application in this cycle uses this CNIC / B-Form.', 3)
                : $this->row('duplicate_cnic', 'Duplicate applicant check', $app->cnic, ['other' => $dupes->pluck('tracking_no')->implode(', ')], 'mismatch', 0, 'Same CNIC / B-Form used on '.$dupes->pluck('tracking_no')->implode(', ').'.', 3);
        }

        foreach (['matric', 'inter'] as $lvl) {
            if ($lvl === 'inter' && ! $needsInter) {
                continue;
            }
            $o = $app->{$lvl.'_obtained'};
            $t = $app->{$lvl.'_total'};
            if ($o !== null && $t) {
                $ok = $o <= $t && $t >= 500 && $t <= 1200 && $o >= $t * 0.33;
                $rows[] = $this->row($lvl.'_marks_valid', ucfirst($lvl).' marks sanity', "{$o} / {$t}", [], $ok ? 'match' : 'mismatch', $ok ? 100 : 0,
                    $ok ? 'Obtained marks are within the board total and pass threshold.' : 'Obtained marks exceed the total or are outside the usual board range.', 2);
            }
        }

        if ($app->date_of_birth) {
            $age = Carbon::parse($app->date_of_birth)->diffInYears(now());
            $minAge = $needsInter ? 16 : 14;
            $ok = $age >= $minAge && $age <= 30;
            $rows[] = $this->row('age_plausible', 'Age plausibility', $app->date_of_birth->format('Y-m-d'), [], $ok ? 'match' : 'partial', $ok ? 100 : 60,
                sprintf('Applicant is %d years old%s.', (int) $age, $ok ? '' : " — unusual for this program (expected {$minAge}–30)"), 1);
        }

        foreach ($app->documents as $doc) {
            if (! $doc->isExtractable()) {
                continue;
            }
            if ($doc->extraction_status === 'skipped') {
                $rows[] = $this->row('doc_'.$doc->type, $doc->label().' — manual check', null, [], 'missing', 0, 'Not read automatically. Compare the uploaded file with the form by hand, then accept.', 1);
            } elseif ($doc->extraction_status === 'failed') {
                $rows[] = $this->row('doc_'.$doc->type, $doc->label().' readability', null, [], 'missing', 0, $doc->extraction_error ?: 'Could not be read.', 1);
            } elseif ($doc->extraction_status === 'done') {
                $issues = array_filter($doc->extracted['issues'] ?? []);
                $typeOk = ($doc->extracted['document_type_matches'] ?? true) !== false;
                $conf = (int) ($doc->confidence ?? 0);
                if (! $typeOk) {
                    $rows[] = $this->row('doc_'.$doc->type, $doc->label().' type', null, [], 'mismatch', 0, 'The uploaded file does not look like a '.$doc->label().'.', 2);
                } elseif ($issues || $conf < 70) {
                    $rows[] = $this->row('doc_'.$doc->type, $doc->label().' quality', null, [], 'partial', max(50, $conf), ($issues ? implode('; ', $issues) : 'Low reading confidence').' ('.$conf.'%).', 1);
                }
            }
        }

        // ---------- Persist (keep manual overrides) ----------
        $app->checks()->delete();
        $points = 0;
        $weights = 0;
        $hasMismatch = false;
        $hasMissing = false;
        $hasPartial = false;
        foreach ($rows as $r) {
            $weight = $r['weight'];
            unset($r['weight']);
            if ($ov = $overrides->get($r['field'])) {
                $r['overridden'] = true;
                $r['overridden_by'] = $ov->overridden_by;
                $r['note'] = $ov->note;
            }
            $app->checks()->create($r);

            $effective = ! empty($r['overridden']) ? 'match' : $r['result'];
            $points += $weight * match ($effective) {
                'match' => 100,
                'partial' => max(60, $r['similarity']),
                'missing' => 40,
                default => 0,
            };
            $weights += $weight;
            $hasMismatch = $hasMismatch || $effective === 'mismatch';
            $hasMissing = $hasMissing || $effective === 'missing';
            $hasPartial = $hasPartial || $effective === 'partial';
        }

        // Weighted agreement, then a firm penalty per open problem so a single
        // tampered value can never hide behind many matching ones.
        $mismatches = 0;
        $missing = 0;
        foreach ($app->checks()->get() as $c) {
            if (! $c->overridden) {
                $mismatches += $c->result === 'mismatch' ? 1 : 0;
                $missing += $c->result === 'missing' ? 1 : 0;
            }
        }
        $score = $weights ? (int) max(0, round($points / $weights) - 15 * $mismatches - 4 * $missing) : 0;
        $status = match (true) {
            $weights === 0 => 'pending',
            $hasMismatch => 'mismatch',
            $score >= 90 && ! $hasMissing && ! $hasPartial => 'clear',
            default => 'review',
        };

        $app->verification_score = $score;
        $app->verification_status = $status;

        // Move the application along the pipeline. The AI only recommends: "clear" waits for an
        // officer to press Verify (a forged or prompt-injected document must never reach a merit
        // list on the model's word alone). Drafts and decisions already taken are left alone.
        if (in_array($app->status, ['submitted', 'under_review', 'flagged'], true)) {
            $app->status = $status === 'mismatch' ? 'flagged' : 'under_review';
        }
        $app->save();

        return $app;
    }

    /** Similarity 0-100 between a form value and a document value for a field kind. */
    public static function compare(string $kind, string $a, string $b): int
    {
        return match ($kind) {
            'name' => Normalizer::nameSimilarity($a, $b),
            'digits' => Normalizer::digits($a) !== '' && Normalizer::digits($a) === Normalizer::digits($b) ? 100 : 0,
            'number' => is_numeric($a) && is_numeric($b) && (int) $a === (int) $b ? 100 : 0,
            'date' => Normalizer::date($a) !== null && Normalizer::date($a) === Normalizer::date($b) ? 100 : 0,
            'board' => self::textSimilarity(Normalizer::board($a), Normalizer::board($b)),
            default => self::textSimilarity(Normalizer::text($a), Normalizer::text($b)),
        };
    }

    private static function textSimilarity(string $a, string $b): int
    {
        if ($a === '' || $b === '') {
            return 0;
        }
        if ($a === $b || str_contains($a, $b) || str_contains($b, $a)) {
            return 100;
        }
        similar_text($a, $b, $p);

        return (int) round($p);
    }

    private function row(string $field, string $label, ?string $form, array $docValues, string $result, int $similarity, ?string $note, int $weight): array
    {
        return [
            'field' => $field,
            'label' => $label,
            'form_value' => $form,
            'document_values' => $docValues ?: null,
            'result' => $result,
            'similarity' => $similarity,
            'note' => $note,
            'weight' => $weight,
        ];
    }
}
