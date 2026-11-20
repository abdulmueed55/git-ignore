<?php

namespace App\Http\Controllers\Attendance;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Student;
use App\Services\Attendance\AttendanceStats;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AnalyticsController extends Controller
{
    use Filters;

    public function __invoke(Request $request, AttendanceStats $stats): View
    {
        $f = $this->filters($request);
        $threshold = config('attendance.threshold');
        $today = today()->toDateString();

        $range = $stats->totals($f);
        $prevTo = Carbon::parse($f['from'])->subDay();
        $prev = $stats->totals([...$f, 'from' => $prevTo->copy()->subDays(Carbon::parse($f['from'])->diffInDays(Carbon::parse($f['to']))), 'to' => $prevTo->toDateString()]);
        $todayTotals = $stats->totals([...$f, 'from' => $today, 'to' => $today]);

        // Register compliance (lectures marked vs. timetable) in range
        $courses = Course::with(['teacher', 'subject', 'section.program'])
            ->when($f['course_ids'] !== null, fn ($q) => $q->whereIn('id', $f['course_ids'] ?: [0]))
            ->when($f['section_id'], fn ($q) => $q->where('section_id', $f['section_id']))
            ->when($f['program_id'], fn ($q) => $q->whereHas('section', fn ($s) => $s->where('program_id', $f['program_id'])))
            ->get();
        $scheduled = $stats->scheduled($courses->pluck('id')->all(), $f['from'], $f['to']);
        $marked = $stats->lectures([...$f, 'course_ids' => $courses->pluck('id')->all()])
            ->selectRaw('l.course_id, count(*) n')->groupBy('l.course_id')->pluck('n', 'l.course_id');
        $sched = array_sum($scheduled);
        $compliance = $sched ? min(100, round($marked->sum() / $sched * 100, 1)) : null;

        $teachers = $courses->groupBy('teacher_id')->map(function ($cs) use ($scheduled, $marked) {
            $s = $cs->sum(fn ($c) => $scheduled[$c->id] ?? 0);
            $m = $cs->sum(fn ($c) => $marked[$c->id] ?? 0);

            return ['teacher' => $cs->first()->teacher, 'courses' => $cs->count(), 'scheduled' => $s, 'marked' => $m,
                'missing' => max(0, $s - $m), 'rate' => $s ? min(100, round($m / $s * 100)) : null];
        })->sortBy('rate')->values();

        // Students below threshold in scope
        $studentIds = Student::where('status', 'active')
            ->when($f['section_id'], fn ($q) => $q->where('section_id', $f['section_id']))
            ->when($f['program_id'], fn ($q) => $q->where('program_id', $f['program_id']))
            ->when($f['course_ids'] !== null, fn ($q) => $q->whereIn('section_id', $courses->pluck('section_id')->unique()))
            ->pluck('id')->all();
        $rates = collect($stats->studentRates($studentIds, ['course_ids' => $f['course_ids']]))->pluck('rate')->filter(fn ($r) => $r !== null);
        $below = $rates->filter(fn ($r) => $r < $threshold)->count();
        $distribution = [
            '< 60%' => $rates->filter(fn ($r) => $r < 60)->count(),
            '60–74%' => $rates->filter(fn ($r) => $r >= 60 && $r < 75)->count(),
            '75–84%' => $rates->filter(fn ($r) => $r >= 75 && $r < 85)->count(),
            '85–94%' => $rates->filter(fn ($r) => $r >= 85 && $r < 95)->count(),
            '95%+' => $rates->filter(fn ($r) => $r >= 95)->count(),
        ];

        $daily = $stats->daily($f);

        // Weekday pattern (weighted from lecture counters)
        $weekdayRaw = [];
        foreach ($stats->lectures($f)->selectRaw('l.held_on d, SUM(l.present_count + l.late_count) p, SUM(l.present_count + l.late_count + l.absent_count) c')->groupBy('l.held_on')->get() as $r) {
            $w = Carbon::parse($r->d)->dayOfWeekIso;
            $weekdayRaw[$w][0] = ($weekdayRaw[$w][0] ?? 0) + $r->p;
            $weekdayRaw[$w][1] = ($weekdayRaw[$w][1] ?? 0) + $r->c;
        }
        $weekdays = [];
        foreach ([1 => 'Mon', 2 => 'Tue', 3 => 'Wed', 4 => 'Thu', 5 => 'Fri'] as $i => $label) {
            $weekdays[$label] = isset($weekdayRaw[$i]) ? AttendanceStats::rate($weekdayRaw[$i][0], $weekdayRaw[$i][1]) : null;
        }

        // Heatmap: 18 weeks ending at "to"
        $heatEnd = Carbon::parse($f['to']);
        $first = $stats->lectures([...$f, 'from' => null, 'to' => null])->min('l.held_on');
        $heatStart = $heatEnd->copy()->subWeeks(25)->startOfWeek()->max($first ? Carbon::parse($first)->startOfWeek() : $heatEnd->copy()->startOfWeek());
        $heatDaily = $stats->daily([...$f, 'from' => $heatStart->toDateString(), 'to' => $heatEnd->toDateString()]);
        $heat = [];
        foreach (CarbonPeriod::create($heatStart, $heatEnd->copy()->endOfWeek()->subDays(2)) as $d) {
            if ($d->isWeekend()) {
                continue;
            }
            $key = $d->toDateString();
            $heat[$d->copy()->startOfWeek()->toDateString()][$d->dayOfWeekIso] = [
                'date' => $key,
                'rate' => $heatDaily[$key] ?? null,
                'holiday' => config('attendance.holidays')[$key] ?? null,
                'future' => $d->isAfter($heatEnd),
            ];
        }

        return view('attendance.analytics', [
            'f' => $f,
            'options' => $this->filterOptions($request),
            'threshold' => $threshold,
            'range' => $range,
            'prev' => $prev,
            'todayTotals' => $todayTotals,
            'compliance' => $compliance,
            'scheduledCount' => $sched,
            'markedCount' => $marked->sum(),
            'below' => $below,
            'studentCount' => $rates->count(),
            'distribution' => $distribution,
            'daily' => $daily,
            'byProgram' => $stats->grouped('program', $f),
            'bySection' => $stats->grouped('section', $f),
            'bySubject' => $stats->grouped('subject', $f),
            'weekdays' => $weekdays,
            'heat' => $heat,
            'teachers' => $teachers,
        ]);
    }
}
