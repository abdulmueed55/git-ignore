<?php

namespace App\Http\Controllers\Attendance;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Services\Attendance\AttendanceStats;
use App\Services\Attendance\Scope;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class StudentAttendanceController extends Controller
{
    public function __invoke(Request $request, Student $student, AttendanceStats $stats): View
    {
        abort_unless(Scope::canViewStudent($request->user(), $student), 403, 'This student is not in any of your classes.');
        $student->load(['section.program', 'section.incharge', 'leaveRequests' => fn ($q) => $q->latest('from_date'), 'alerts' => fn ($q) => $q->latest()->limit(12)]);

        $overall = $stats->studentRates([$student->id])[$student->id] ?? ['P' => 0, 'A' => 0, 'L' => 0, 'E' => 0, 'counted' => 0, 'rate' => null];

        $subjects = DB::table('attendance_records as r')
            ->join('lectures as l', 'l.id', '=', 'r.lecture_id')
            ->join('courses as c', 'c.id', '=', 'l.course_id')
            ->join('subjects as s', 's.id', '=', 'c.subject_id')
            ->leftJoin('users as u', 'u.id', '=', 'c.teacher_id')
            ->where('r.student_id', $student->id)
            ->selectRaw("s.name, u.name as teacher,
                SUM(CASE WHEN r.status IN ('P','L') THEN 1 ELSE 0 END) p,
                SUM(CASE WHEN r.status = 'A' THEN 1 ELSE 0 END) a,
                SUM(CASE WHEN r.status = 'E' THEN 1 ELSE 0 END) e")
            ->groupBy('s.name', 'u.name')->get()
            ->map(fn ($r) => ['name' => $r->name, 'teacher' => $r->teacher, 'P' => (int) $r->p, 'A' => (int) $r->a, 'E' => (int) $r->e, 'rate' => AttendanceStats::rate($r->p, $r->p + $r->a)])
            ->sortBy('rate')->values();

        try {
            $month = $request->filled('month') ? Carbon::parse($request->input('month').'-01') : today()->startOfMonth();
        } catch (\Throwable) {
            $month = today()->startOfMonth();
        }
        $days = $stats->dayStatuses([$student->id])[$student->id] ?? [];
        $streak = AttendanceStats::streaks($days);

        $absences = DB::table('attendance_records as r')
            ->join('lectures as l', 'l.id', '=', 'r.lecture_id')
            ->join('courses as c', 'c.id', '=', 'l.course_id')
            ->join('subjects as s', 's.id', '=', 'c.subject_id')
            ->where('r.student_id', $student->id)->where('r.status', 'A')
            ->orderByDesc('l.held_on')->orderByDesc('l.starts_at')->limit(8)
            ->get(['l.held_on', 'l.starts_at', 's.name', 'l.topic']);

        return view('attendance.student', compact('student', 'overall', 'subjects', 'month', 'days', 'streak', 'absences') + [
            'threshold' => config('attendance.threshold'),
            'weekly' => AttendanceStats::weekly($days, 12),
        ]);
    }
}
