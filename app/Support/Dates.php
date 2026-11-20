<?php

namespace App\Support;

use Carbon\CarbonInterface;
use IntlDateFormatter;

class Dates
{
    /** e.g. "18 Jumada I 1448 AH" — Umm al-Qura calendar via ICU. */
    public static function hijri(CarbonInterface $date): string
    {
        if (! class_exists(IntlDateFormatter::class)) {
            return '';
        }
        $fmt = new IntlDateFormatter('en_PK@calendar=islamic-umalqura', IntlDateFormatter::LONG, IntlDateFormatter::NONE, $date->timezoneName, IntlDateFormatter::TRADITIONAL, 'd MMMM y');

        return trim((string) $fmt->format($date->getTimestamp())).' AH';
    }

    public static function greeting(?CarbonInterface $now = null): string
    {
        $h = ($now ?? now())->hour;

        return match (true) {
            $h < 12 => 'Good morning',
            $h < 17 => 'Good afternoon',
            default => 'Good evening',
        };
    }
}
