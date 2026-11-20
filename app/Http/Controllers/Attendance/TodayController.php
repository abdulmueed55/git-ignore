<?php

namespace App\Http\Controllers\Attendance;

use App\Http\Controllers\Controller;
use App\Models\Lecture;
use App\Models\Student;
use App\Models\TimetableSlot;
use App\Services\Attendance\AttendanceStats;
use App\Services\Attendance\Scope;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** "Today": the teacher's period timeline, or the college-wide register board for leadership. */
class TodayController extends Controller
{
    public function __invoke(Request $request, AttendanceStats $stats): View
    {
        $user = $request->user();
        $now = now();
        $date = $this->date($request);
        $isToday = $date->isSameDay($now);
        $holiday = config('attendance.holidays')[$date->toDateString()] ?? null;
        $courseIds = Scope::courseIds($user);

        $slots = TimetableSlot::query()
            ->where('weekday', $date->dayOfWeekIso)
            ->when($courseIds !== null, fn ($q) => $q->whereIn('course_id', $courseIds ?: [0]))
            ->whereHas('course.section.academicSession', fn ($q) => $q->where('starts_on', '<=', $date->toDateString()))
            ->with(['course.subject', 'course.teacher', 'course.section.program'])
            ->orderBy('starts_at')
            ->get();

        $lectures = Lecture::where('held_on', $date->toDateString())
            ->whereIn('course_id', $slots->pluck('course_id')->unique())
            ->get()->keyBy(fn ($l) => $l->timetable_slot_id ?: 'c'.$l->course_id.'@'.$l->starts_at);

        $items = $slots->map(function (TimetableSlot $slot) use ($lectures, $date, $now, $isToday, $holiday) {
            $start = Carbon::parse($date->toDateString().' '.$slot->starts_at);
            $end = Carbon::parse($date->toDateString().' '.$slot->ends_at);
            $lecture = $lectures->get($slot->id);
            $state = match (true) {
                $lecture !== null => 'marked',
                $holiday !== null => 'holiday',
                $date->isFuture() && ! $isToday => 'upcoming',
                $isToday && $now->between($start, $end) => 'now',
                $isToday && $now->lt($start) => 'upcoming',
                default => 'overdue',
            };

            return (object) ['slot' => $slot, 'course' => $slot->course, 'lecture' => $lecture, 'state' => $state, 'start' => $start, 'end' => $end];
        });

        $summary = [
            'due' => $items->whereIn('state', ['marked', 'now', 'overdue'])->count(),
            'marked' => $items->where('state', 'marked')->count(),
            'overdue' => $items->where('state', 'overdue')->count(),
            'upcoming' => $items->where('state', 'upcoming')->count(),
        ];
        $summary['compliance'] = $summary['due'] ? round($summary['marked'] / $summary['due'] * 100) : null;
        $dayTotals = $stats->totals(['course_ids' => $courseIds, 'from' => $date->toDateString(), 'to' => $date->toDateString()]);

        $board = null;
        $byProgram = [];
        if ($user->isLeadership()) {
            $periods = $items->map(fn ($i) => substr($i->slot->starts_at, 0, 5))->unique()->sort()->values();
            $board = [
                'periods' => $periods,
                'rows' => $items->groupBy(fn ($i) => $i->course->section_id)
                    ->map(fn ($g) => ['section' => $g->first()->course->section, 'cells' => $g->keyBy(fn ($i) => substr($i->slot->starts_at, 0, 5))])
                    ->sortBy(fn ($r) => $r['section']->program->code.$r['section']->name)->values(),
            ];
            $byProgram = $stats->grouped('program', ['from' => $date->toDateString(), 'to' => $date->toDateString()]);
        }

        $rosterCounts = Student::where('status', 'active')->selectRaw('section_id, count(*) n')->groupBy('section_id')->pluck('n', 'section_id');

        $myStudents = Student::where('status', 'active')
            ->when(Scope::sectionIds($user) !== null, fn ($q) => $q->whereIn('section_id', Scope::sectionIds($user) ?: [0]))
            ->pluck('id')->all();
        $atRisk = collect($stats->studentRates($myStudents, ['course_ids' => $courseIds]))
            ->filter(fn ($r) => $r['rate'] !== null && $r['rate'] < config('attendance.threshold'))->count();

        return view('attendance.index', compact('date', 'isToday', 'holiday', 'items', 'summary', 'dayTotals', 'board', 'byProgram', 'atRisk', 'user', 'rosterCounts'));
    }

    private function date(Request $request): Carbon
    {
        try {
            return $request->filled('date') ? Carbon::parse($request->string('date'))->startOfDay() : today();
        } catch (\Throwable) {
            return today();
        }
    }
}
