<?php

namespace App\Services\Student;

use App\Models\AttendanceRecord;
use App\Models\Course;
use App\Models\Student;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/** What a student sees about themselves: subjects, attendance per subject, recent marks. */
class StudentPortal
{
    /** @return Collection<int, Course> courses of the student's class */
    public function courses(Student $s): Collection
    {
        return Course::with(['subject', 'teacher'])->where('section_id', $s->section_id ?: 0)->get()->sortBy('subject.name')->values();
    }

    /** @return array{rate: ?float, P:int, A:int, L:int, E:int, counted:int} */
    public function overall(Student $s): array
    {
        $r = $this->rates($s)->reduce(fn ($c, $x) => ['P' => $c['P'] + $x['P'], 'A' => $c['A'] + $x['A'], 'L' => $c['L'] + $x['L'], 'E' => $c['E'] + $x['E']], ['P' => 0, 'A' => 0, 'L' => 0, 'E' => 0]);
        $counted = $r['P'] + $r['L'] + $r['A'];

        return $r + ['counted' => $counted, 'rate' => $counted ? round(($r['P'] + $r['L']) / $counted * 100, 1) : null];
    }

    /** @return Collection<int, array> per course_id: P, A, L, E, counted, rate */
    public function rates(Student $s): Collection
    {
        return DB::table('attendance_records as r')->join('lectures as l', 'l.id', '=', 'r.lecture_id')
            ->where('r.student_id', $s->id)
            ->selectRaw("l.course_id,
                SUM(CASE WHEN r.status = 'P' THEN 1 ELSE 0 END) p, SUM(CASE WHEN r.status = 'A' THEN 1 ELSE 0 END) a,
                SUM(CASE WHEN r.status = 'L' THEN 1 ELSE 0 END) lt, SUM(CASE WHEN r.status = 'E' THEN 1 ELSE 0 END) e")
            ->groupBy('l.course_id')->get()
            ->mapWithKeys(function ($r) {
                $counted = $r->p + $r->lt + $r->a;

                return [$r->course_id => ['P' => (int) $r->p, 'A' => (int) $r->a, 'L' => (int) $r->lt, 'E' => (int) $r->e, 'counted' => $counted,
                    'rate' => $counted ? round(($r->p + $r->lt) / $counted * 100, 1) : null]];
            });
    }

    /** Latest register entries (newest first). */
    public function recent(Student $s, int $limit = 12): Collection
    {
        return AttendanceRecord::with('lecture.course.subject')->where('student_id', $s->id)
            ->join('lectures', 'lectures.id', '=', 'attendance_records.lecture_id')
            ->orderByDesc('lectures.held_on')->orderByDesc('lectures.starts_at')
            ->select('attendance_records.*')->limit($limit)->get();
    }
}
