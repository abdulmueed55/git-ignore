<?php

namespace App\Services\Attendance;

use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * All attendance aggregation lives here and runs in SQL.
 * Rate = (P + L) / (P + L + A) × 100 — approved leave (E) is excluded.
 *
 * Filters accepted by most methods:
 *   course_ids (array|null), program_id, section_id, from (Y-m-d), to (Y-m-d)
 */
class AttendanceStats
{
    public const PRESENT = "SUM(CASE WHEN r.status IN ('P','L') THEN 1 ELSE 0 END)";

    public const COUNTED = "SUM(CASE WHEN r.status IN ('P','L','A') THEN 1 ELSE 0 END)";

    public static function rate(int|float $present, int|float $counted): ?float
    {
        return $counted > 0 ? round($present / $counted * 100, 1) : null;
    }

    /** Lectures joined with course + section, filtered. */
    public function lectures(array $f = []): Builder
    {
        $q = DB::table('lectures as l')
            ->join('courses as c', 'c.id', '=', 'l.course_id')
            ->join('sections as s', 's.id', '=', 'c.section_id');

        if (array_key_exists('course_ids', $f) && $f['course_ids'] !== null) {
            $q->whereIn('l.course_id', $f['course_ids'] ?: [0]);
        }
        if (! empty($f['program_id'])) {
            $q->where('s.program_id', $f['program_id']);
        }
        if (! empty($f['section_id'])) {
            $q->where('s.id', $f['section_id']);
        }
        if (! empty($f['from'])) {
            $q->where('l.held_on', '>=', $f['from']);
        }
        if (! empty($f['to'])) {
            $q->where('l.held_on', '<=', $f['to']);
        }

        return $q;
    }

    /** Sum of the denormalised lecture counters → [present, counted, lectures]. */
    public function totals(array $f = []): array
    {
        $row = $this->lectures($f)->selectRaw('SUM(l.present_count + l.late_count) p, SUM(l.present_count + l.late_count + l.absent_count) c, COUNT(*) n')->first();

        return ['present' => (int) $row->p, 'counted' => (int) $row->c, 'lectures' => (int) $row->n, 'rate' => self::rate((int) $row->p, (int) $row->c)];
    }

    /** [Y-m-d => rate] for each day that had lectures. */
    public function daily(array $f = []): array
    {
        return $this->lectures($f)
            ->selectRaw('l.held_on d, SUM(l.present_count + l.late_count) p, SUM(l.present_count + l.late_count + l.absent_count) c')
            ->groupBy('l.held_on')->orderBy('l.held_on')->get()
            ->mapWithKeys(fn ($r) => [substr($r->d, 0, 10) => self::rate((int) $r->p, (int) $r->c)])
            ->all();
    }

    /** Rates grouped by program | section | subject | teacher. */
    public function grouped(string $by, array $f = []): array
    {
        $q = $this->lectures($f);
        [$key, $label] = match ($by) {
            'program' => ['p.id', 'p.code'],
            'section' => ['s.id', 's.name'],
            'subject' => ['sub.id', 'sub.name'],
            'teacher' => ['u.id', 'u.name'],
        };
        if ($by === 'program' || $by === 'section') {
            $q->join('programs as p', 'p.id', '=', 's.program_id');
        }
        if ($by === 'subject') {
            $q->join('subjects as sub', 'sub.id', '=', 'c.subject_id');
        }
        if ($by === 'teacher') {
            $q->join('users as u', 'u.id', '=', 'c.teacher_id');
        }
        $extra = $by === 'section' ? ', p.code as program_code' : '';

        return $q->selectRaw("$key as id, $label as label$extra, SUM(l.present_count + l.late_count) p, SUM(l.present_count + l.late_count + l.absent_count) c, COUNT(*) n")
            ->groupBy($key, $label, ...($by === 'section' ? ['p.code'] : []))
            ->get()
            ->map(fn ($r) => [
                'id' => $r->id,
                'label' => $by === 'section' ? $r->program_code.' · '.$r->label : $r->label,
                'rate' => self::rate((int) $r->p, (int) $r->c),
                'lectures' => (int) $r->n,
            ])
            ->sortByDesc('rate')->values()->all();
    }

    /**
     * Per-student totals: [student_id => [P, A, L, E, counted, rate]].
     * $studentIds limits the students; course filter etc. via $f.
     */
    public function studentRates(?array $studentIds = null, array $f = []): array
    {
        $q = DB::table('attendance_records as r')->join('lectures as l', 'l.id', '=', 'r.lecture_id');
        if ($studentIds !== null) {
            $q->whereIn('r.student_id', $studentIds ?: [0]);
        }
        if (array_key_exists('course_ids', $f) && $f['course_ids'] !== null) {
            $q->whereIn('l.course_id', $f['course_ids'] ?: [0]);
        }
        if (! empty($f['from'])) {
            $q->where('l.held_on', '>=', $f['from']);
        }
        if (! empty($f['to'])) {
            $q->where('l.held_on', '<=', $f['to']);
        }

        return $q->selectRaw("r.student_id,
                SUM(CASE WHEN r.status = 'P' THEN 1 ELSE 0 END) p,
                SUM(CASE WHEN r.status = 'A' THEN 1 ELSE 0 END) a,
                SUM(CASE WHEN r.status = 'L' THEN 1 ELSE 0 END) lt,
                SUM(CASE WHEN r.status = 'E' THEN 1 ELSE 0 END) e")
            ->groupBy('r.student_id')->get()
            ->mapWithKeys(fn ($r) => [$r->student_id => [
                'P' => (int) $r->p, 'A' => (int) $r->a, 'L' => (int) $r->lt, 'E' => (int) $r->e,
                'counted' => $r->p + $r->lt + $r->a,
                'rate' => self::rate($r->p + $r->lt, $r->p + $r->lt + $r->a),
            ]])->all();
    }

    /**
     * Day-level history for a few students (defaulters, profile, alert checks):
     * [student_id => [Y-m-d => 'P'|'A'|'E'|'M' (mixed)]] — 'A' means absent in every lecture that day.
     */
    public function dayStatuses(array $studentIds, ?string $from = null, ?string $to = null): array
    {
        if (! $studentIds) {
            return [];
        }
        $q = DB::table('attendance_records as r')->join('lectures as l', 'l.id', '=', 'r.lecture_id')
            ->whereIn('r.student_id', $studentIds)
            ->selectRaw("r.student_id, l.held_on d,
                SUM(CASE WHEN r.status IN ('P','L') THEN 1 ELSE 0 END) p,
                SUM(CASE WHEN r.status = 'A' THEN 1 ELSE 0 END) a,
                SUM(CASE WHEN r.status = 'E' THEN 1 ELSE 0 END) e")
            ->groupBy('r.student_id', 'l.held_on')->orderBy('l.held_on');
        if ($from) {
            $q->where('l.held_on', '>=', $from);
        }
        if ($to) {
            $q->where('l.held_on', '<=', $to);
        }

        $out = [];
        foreach ($q->get() as $r) {
            $out[$r->student_id][substr($r->d, 0, 10)] = match (true) {
                $r->p == 0 && $r->a > 0 => 'A',
                $r->p == 0 && $r->e > 0 => 'E',
                $r->a > 0 => 'M',
                default => 'P',
            };
        }

        return $out;
    }

    /** Longest / current absence streak (in days) and last day present, from a dayStatuses() row. */
    public static function streaks(array $days): array
    {
        $longest = $run = 0;
        $lastPresent = null;
        foreach ($days as $date => $s) {
            if ($s === 'A') {
                $longest = max($longest, ++$run);
            } elseif ($s !== 'E') {
                $run = 0;
                $lastPresent = $date;
            }
        }

        return ['longest' => $longest, 'current' => $run, 'last_present' => $lastPresent];
    }

    /** Weekly rates for a student's day statuses (for sparklines). */
    public static function weekly(array $days, int $weeks = 8): array
    {
        $buckets = [];
        foreach ($days as $date => $s) {
            if ($s === 'E') {
                continue;
            }
            $w = Carbon::parse($date)->startOfWeek()->toDateString();
            $buckets[$w][0] = ($buckets[$w][0] ?? 0) + ($s === 'A' ? 0 : ($s === 'M' ? 0.5 : 1));
            $buckets[$w][1] = ($buckets[$w][1] ?? 0) + 1;
        }
        ksort($buckets);

        return array_map(fn ($b) => round($b[0] / max(1, $b[1]) * 100), array_slice($buckets, -$weeks));
    }

    /**
     * How many lectures were scheduled (per timetable) between two dates, per course.
     * Holidays and days before the section's session started are excluded.
     * Lectures today only count once their start time has passed.
     */
    public function scheduled(?array $courseIds, string $from, string $to): array
    {
        $holidays = array_keys(config('attendance.holidays'));
        $now = now();
        $q = DB::table('timetable_slots as t')
            ->join('courses as c', 'c.id', '=', 't.course_id')
            ->join('sections as s', 's.id', '=', 'c.section_id')
            ->join('academic_sessions as a', 'a.id', '=', 's.academic_session_id')
            ->select('t.course_id', 't.weekday', 't.starts_at', 'a.starts_on');
        if ($courseIds !== null) {
            $q->whereIn('t.course_id', $courseIds ?: [0]);
        }
        $slots = $q->get();

        $days = [];
        foreach (CarbonPeriod::create($from, min($to, $now->toDateString())) as $d) {
            if (! $d->isWeekend() && ! in_array($d->toDateString(), $holidays, true)) {
                $days[$d->dayOfWeekIso][] = $d->toDateString();
            }
        }

        $out = [];
        foreach ($slots as $s) {
            foreach ($days[$s->weekday] ?? [] as $date) {
                if ($date < substr($s->starts_on, 0, 10)) {
                    continue;
                }
                if ($date === $now->toDateString() && Carbon::parse($date.' '.$s->starts_at)->greaterThan($now)) {
                    continue;
                }
                $out[$s->course_id] = ($out[$s->course_id] ?? 0) + 1;
            }
        }

        return $out;
    }

    /** Heatmap level 1–4 for a rate. */
    public static function level(?float $rate): int
    {
        return match (true) {
            $rate === null => 0,
            $rate < 75 => 1,
            $rate < 82 => 2,
            $rate < 87 => 3,
            default => 4,
        };
    }
}
