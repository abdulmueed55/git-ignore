<?php

namespace App\Http\Controllers\Attendance;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Section;
use App\Models\Student;
use App\Services\Attendance\AttendanceStats;
use App\Services\Attendance\Scope;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Classic monthly register: students × lecture dates. */
class RegisterController extends Controller
{
    public function show(Request $request): View
    {
        return view('attendance.register', $this->sheet($request));
    }

    public function export(Request $request): StreamedResponse
    {
        $d = $this->sheet($request);
        $name = 'register-'.str($d['section']->label())->slug().'-'.$d['month']->format('Y-m').'.csv';

        return response()->streamDownload(function () use ($d) {
            $out = fopen('php://output', 'w');
            fputcsv($out, array_merge(['Roll no', 'Name'], array_map(fn ($c) => $c['label'], $d['columns']), ['Present', 'Absent', 'Leave', '%']));
            foreach ($d['students'] as $s) {
                $t = $d['totals'][$s->id];
                fputcsv($out, array_merge([$s->roll_no, $s->name], array_map(fn ($c) => $d['grid'][$s->id][$c['key']] ?? '', $d['columns']), [$t['p'], $t['a'], $t['e'], $t['rate']]));
            }
            fclose($out);
        }, $name, ['Content-Type' => 'text/csv']);
    }

    private function sheet(Request $request): array
    {
        $user = $request->user();
        $sectionIds = Scope::sectionIds($user);
        $courseIds = Scope::courseIds($user);

        $sections = Section::with('program')->when($sectionIds !== null, fn ($q) => $q->whereIn('id', $sectionIds ?: [0]))->get()->sortBy(fn ($s) => $s->program->code.$s->name)->values();
        abort_if($sections->isEmpty(), 404, 'No classes are assigned to you yet.');
        $section = $sections->firstWhere('id', $request->integer('section_id')) ?? $sections->first();

        $courses = Course::with('subject')->where('section_id', $section->id)
            ->when($courseIds !== null, fn ($q) => $q->whereIn('id', $courseIds ?: [0]))->get();
        $course = $courses->firstWhere('id', $request->integer('course_id'));
        if (! $course && $courseIds !== null) {
            $course = $courses->first(); // teachers always see their own subject's register
        }

        try {
            $month = $request->filled('month') ? Carbon::parse($request->input('month').'-01')->startOfMonth() : today()->startOfMonth();
        } catch (\Throwable) {
            $month = today()->startOfMonth();
        }
        $from = $month->toDateString();
        $to = $month->copy()->endOfMonth()->toDateString();

        $students = Student::where('section_id', $section->id)->where('status', 'active')->orderBy('roll_no')->get();
        $grid = [];
        $columns = [];

        if ($course) {
            $lectures = $course->lectures()->whereBetween('held_on', [$from, $to])->orderBy('held_on')->orderBy('starts_at')->get();
            foreach ($lectures as $l) {
                $columns[] = ['key' => $l->id, 'day' => $l->held_on->format('j'), 'dow' => $l->held_on->format('D')[0], 'label' => $l->held_on->format('d M').' '.substr($l->starts_at, 0, 5), 'topic' => $l->topic];
            }
            DB::table('attendance_records')->whereIn('lecture_id', $lectures->pluck('id'))->get(['lecture_id', 'student_id', 'status'])
                ->each(function ($r) use (&$grid) {
                    $grid[$r->student_id][$r->lecture_id] = $r->status;
                });
        } else {
            $days = app(AttendanceStats::class)->dayStatuses($students->pluck('id')->all(), $from, $to);
            $dates = collect($days)->flatMap(fn ($d) => array_keys($d))->unique()->sort()->values();
            foreach ($dates as $date) {
                $c = Carbon::parse($date);
                $columns[] = ['key' => $date, 'day' => $c->format('j'), 'dow' => $c->format('D')[0], 'label' => $c->format('d M'), 'topic' => null];
            }
            foreach ($days as $sid => $d) {
                foreach ($d as $date => $s) {
                    $grid[$sid][$date] = $s === 'M' ? 'P' : $s; // partial days count as present on the day sheet
                }
            }
        }

        $totals = [];
        foreach ($students as $s) {
            $row = array_count_values($grid[$s->id] ?? []);
            $p = ($row['P'] ?? 0) + ($row['L'] ?? 0);
            $a = $row['A'] ?? 0;
            $totals[$s->id] = ['p' => $p, 'a' => $a, 'e' => $row['E'] ?? 0, 'rate' => AttendanceStats::rate($p, $p + $a)];
        }

        return compact('sections', 'section', 'courses', 'course', 'month', 'students', 'columns', 'grid', 'totals') + ['threshold' => config('attendance.threshold')];
    }
}
