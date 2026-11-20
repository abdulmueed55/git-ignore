<?php

namespace App\Services\Admissions;

use Carbon\Carbon;
use Throwable;

/** Normalisation + similarity helpers tuned for Pakistani names and documents. */
class Normalizer
{
    private const NAME_ALIASES = [
        '/\b(muhammad|mohammad|mohammed|muhammed|mohamad|muhamad|mohd|muhd|md|m)\b/' => 'muhammad',
        '/\b(abdul|abd|abdal|abdel)\b/' => 'abdul',
        '/\b(ur|ul|ud|us|uz|un)\b/' => 'ul',
        '/\b(chaudhry|chaudhary|chaudri|ch)\b/' => 'chaudhry',
        '/\b(syed|sayed|syd|sayyed)\b/' => 'syed',
        '/\b(hussain|husain|hussein|hossain)\b/' => 'hussain',
        '/\b(ahmed|ahmad)\b/' => 'ahmed',
        '/\b(mehmood|mahmood|mahmud|mehmud)\b/' => 'mehmood',
        '/\b(rehman|rahman|rehaman)\b/' => 'rehman',
    ];

    public static function name(?string $v): string
    {
        $v = mb_strtolower(trim((string) $v));
        $v = preg_replace('/^((mr|ms|mrs|miss|dr|prof|hafiz|haji)\.?\s+)+/u', '', $v);
        $v = preg_replace('/[^a-z\s]/u', ' ', $v);
        $v = preg_replace('/\s+/', ' ', $v);
        foreach (self::NAME_ALIASES as $re => $to) {
            $v = preg_replace($re, $to, $v);
        }

        return trim($v);
    }

    public static function digits(?string $v): string
    {
        return preg_replace('/\D/', '', (string) $v);
    }

    public static function text(?string $v): string
    {
        return trim(preg_replace('/\s+/', ' ', mb_strtolower(preg_replace('/[^\pL\pN\s]/u', ' ', (string) $v))));
    }

    /** Board names: "BISE Rawalpindi" == "Board of Intermediate & Secondary Education, Rawalpindi". */
    public static function board(?string $v): string
    {
        $t = self::text($v);
        $t = preg_replace('/\b(board|of|intermediate|and|secondary|education|bise|the)\b/', ' ', $t);
        $t = preg_replace('/\bfederal\b|\bfbise\b|\bfb\b/', 'federal', $t);

        return trim(preg_replace('/\s+/', ' ', $t));
    }

    public static function date(mixed $v): ?string
    {
        if ($v instanceof \DateTimeInterface) {
            return $v->format('Y-m-d');
        }
        $v = trim((string) $v);
        if ($v === '') {
            return null;
        }
        foreach (['Y-m-d', 'd-m-Y', 'd/m/Y', 'd.m.Y', 'j M Y', 'd M Y', 'Y/m/d'] as $fmt) {
            try {
                $d = Carbon::createFromFormat('!'.$fmt, $v);
                if ($d && $d->format($fmt) === $v) {
                    return $d->format('Y-m-d');
                }
            } catch (Throwable) {
            }
        }
        try {
            return Carbon::parse($v)->format('Y-m-d');
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * 0-100 similarity that ignores word order and common spelling variants.
     * Each word of the shorter name is matched to its closest word in the other;
     * a word that is genuinely different (e.g. Shahid vs Sajid) caps the score
     * below the partial threshold, a missing extra word (surname) costs less.
     */
    public static function nameSimilarity(?string $a, ?string $b): int
    {
        $a = self::name($a);
        $b = self::name($b);
        if ($a === '' || $b === '') {
            return 0;
        }
        if ($a === $b) {
            return 100;
        }
        $ta = explode(' ', $a);
        $tb = explode(' ', $b);
        [$short, $long] = count($ta) <= count($tb) ? [$ta, $tb] : [$tb, $ta];

        $best = [];
        foreach ($short as $w) {
            $m = 0;
            foreach ($long as $v) {
                $m = max($m, 1 - levenshtein($w, $v) / max(strlen($w), strlen($v)));
            }
            $best[] = $m;
        }
        $mean = array_sum($best) / count($best);
        $score = $mean * sqrt(count($short) / count($long)) * 100;
        if (min($best) < 0.75) {
            $score = min($score, 70);
        }

        return (int) round($score);
    }
}
