<?php

namespace App\Support;

/** Helpers for CSV exports that staff open in Excel. */
class Csv
{
    /**
     * Neutralise spreadsheet formulas (CSV / formula injection): a cell typed by an
     * applicant as =HYPERLINK(...) must stay text when opened in Excel.
     *
     * @param  array<int, mixed>  $row
     * @return array<int, mixed>
     */
    public static function row(array $row): array
    {
        return array_map([self::class, 'cell'], $row);
    }

    public static function cell(mixed $v): mixed
    {
        if (is_string($v) && $v !== '' && preg_match('/^[=+\-@\t\r|]/', $v) && ! preg_match('/^-?\d+(\.\d+)?$/', $v)) {
            return "'".$v;
        }

        return $v;
    }
}
