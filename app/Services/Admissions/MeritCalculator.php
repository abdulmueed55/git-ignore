<?php

namespace App\Services\Admissions;

use App\Models\AdmissionApplication;

/**
 * Merit = academic percentage per program weights.
 *  - Intermediate programs: matric % (hafiz bonus marks added to obtained).
 *  - ADP / BS: weights['academic'] × inter % + weights['matric'] × matric %.
 */
class MeritCalculator
{
    /** @return array{score: float|null, parts: array<int, array{label: string, value: string}>} */
    public function breakdown(AdmissionApplication $app): array
    {
        $program = $app->program;
        if (! $program) {
            return ['score' => null, 'parts' => []];
        }
        $w = $program->weights();
        $bonus = $app->is_hafiz ? (int) ($w['hafiz_bonus'] ?? 0) : 0;

        $pct = function (?int $obt, ?int $tot) use ($bonus) {
            if ($obt === null || ! $tot) {
                return null;
            }

            return min(100, ($obt + $bonus) / $tot * 100);
        };

        $matric = $pct($app->matric_obtained, $app->matric_total);
        $parts = [];
        if ($bonus) {
            $parts[] = ['label' => 'Hafiz-e-Quran bonus', 'value' => "+{$bonus} marks"];
        }

        if (! $program->basedOnInter()) {
            if ($matric === null) {
                return ['score' => null, 'parts' => $parts];
            }
            $parts[] = ['label' => 'Matric', 'value' => sprintf('%d / %d → %.2f%%', $app->matric_obtained, $app->matric_total, $matric)];

            return ['score' => round($matric, 3), 'parts' => $parts];
        }

        $inter = $pct($app->inter_obtained, $app->inter_total);
        if ($inter === null) {
            return ['score' => null, 'parts' => $parts];
        }
        $wa = (float) ($w['academic'] ?? 1.0);
        $wm = (float) ($w['matric'] ?? 0.0);
        if ($matric === null) {
            $wm = 0.0;
            $wa = 1.0;
        }
        $score = ($wa * $inter + $wm * ($matric ?? 0)) / max(0.0001, $wa + $wm);
        $parts[] = ['label' => sprintf('Intermediate × %d%%', $wa / ($wa + $wm) * 100), 'value' => sprintf('%d / %d → %.2f%%', $app->inter_obtained, $app->inter_total, $inter)];
        if ($wm > 0) {
            $parts[] = ['label' => sprintf('Matric × %d%%', $wm / ($wa + $wm) * 100), 'value' => sprintf('%d / %d → %.2f%%', $app->matric_obtained, $app->matric_total, $matric)];
        }

        return ['score' => round($score, 3), 'parts' => $parts];
    }

    public function score(AdmissionApplication $app): ?float
    {
        return $this->breakdown($app)['score'];
    }

    public function apply(AdmissionApplication $app): AdmissionApplication
    {
        $app->merit_score = $this->score($app);
        $app->save();

        return $app;
    }

    /** Academic percentage the eligibility rule is checked against. */
    public function eligibilityPercentage(AdmissionApplication $app): ?float
    {
        return $app->program?->basedOnInter() ? $app->interPercentage() : $app->matricPercentage();
    }
}
