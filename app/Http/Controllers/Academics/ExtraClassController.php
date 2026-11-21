<?php

namespace App\Http\Controllers\Academics;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Course;
use App\Models\ExtraClass;
use App\Notifications\StaffAlert;
use App\Services\Attendance\AlertService;
use App\Services\Timetable\TeacherSchedule;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Extra, make-up and test classes outside the weekly timetable. */
class ExtraClassController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $courses = $this->courses($request);
        $ids = $courses->pluck('id');

        $upcoming = ExtraClass::with(['course.subject', 'course.teacher', 'course.section.program', 'creator'])
            ->whereIn('course_id', $ids)->active()->whereDate('held_on', '>=', today())
            ->orderBy('held_on')->orderBy('starts_at')->get();
        $recent = ExtraClass::with(['course.subject', 'course.teacher', 'course.section.program'])
            ->whereIn('course_id', $ids)
            ->where(fn ($q) => $q->whereDate('held_on', '<', today())->orWhereNotNull('cancelled_at'))
            ->whereDate('held_on', '>=', today()->subDays(30))
            ->latest('held_on')->latest('starts_at')->limit(12)->get();

        // This week at a glance: regular periods + extra classes, day by day.
        $week = collect(range(0, 6))->map(fn ($i) => today()->addDays($i))
            ->reject(fn (Carbon $d) => $d->isSunday())
            ->map(fn (Carbon $d) => ['date' => $d, 'items' => $user->isLeadership() ? collect() : TeacherSchedule::forTeacher($user->id, $d)]);

        return view('academics.extra.index', [
            'courses' => $courses->groupBy(fn ($c) => $c->section->label()),
            'upcoming' => $upcoming,
            'recent' => $recent,
            'week' => $week,
            'leader' => $user->isLeadership(),
            'kinds' => ExtraClass::KINDS,
        ]);
    }

    public function store(Request $request, AlertService $alerts): RedirectResponse
    {
        $user = $request->user();
        $data = $request->validate([
            'course_id' => ['required', 'integer', Rule::in($this->courses($request)->pluck('id')->all())],
            'held_on' => ['required', 'date', 'after_or_equal:today', 'before_or_equal:+'.config('timetable.extra_max_days_ahead', 60).' days'],
            'starts_at' => ['required', 'date_format:H:i', 'after_or_equal:07:00', 'before:18:00'],
            'ends_at' => ['required', 'date_format:H:i', 'after:starts_at', 'before_or_equal:18:30'],
            'room' => ['required', 'string', 'max:32', 'regex:/^[\pL\pN][\pL\pN \-\/.]*$/u'],
            'kind' => ['required', Rule::in(array_keys(ExtraClass::KINDS))],
            'reason' => ['nullable', 'string', 'max:160'],
            'notify_students' => ['nullable', 'boolean'],
        ], [
            'course_id.in' => 'Choose one of your own subjects.',
            'held_on.after_or_equal' => 'An extra class cannot be scheduled in the past.',
            'ends_at.after' => 'The class must end after it starts.',
            'starts_at.after_or_equal' => 'Classes can be scheduled between 07:00 and 18:30.',
            'starts_at.before' => 'Classes can be scheduled between 07:00 and 18:30.',
            'ends_at.before_or_equal' => 'Classes can be scheduled between 07:00 and 18:30.',
        ]);

        $course = Course::with(['subject', 'teacher', 'section.program'])->findOrFail($data['course_id']);
        $date = Carbon::parse($data['held_on']);
        if ($date->isToday() && $data['starts_at'] <= now()->format('H:i')) {
            return back()->withInput()->withErrors(['starts_at' => 'That time has already passed today.']);
        }
        if ($why = TeacherSchedule::clash($course, $date, $data['starts_at'], $data['ends_at'])) {
            return back()->withInput()->withErrors(['starts_at' => $why]);
        }

        $extra = ExtraClass::create([
            'course_id' => $course->id, 'held_on' => $date->toDateString(), 'starts_at' => $data['starts_at'], 'ends_at' => $data['ends_at'],
            'room' => $data['room'], 'kind' => $data['kind'], 'reason' => $data['reason'] ?? null, 'created_by' => $user->id,
        ]);

        $sms = 0;
        if ($request->boolean('notify_students')) {
            $sms = $alerts->notifyExtraClass($extra);
            $extra->update(['students_notified' => true]);
        }
        $this->tellTeacher($extra, 'scheduled', $user);
        ActivityLog::record('timetable.extra_scheduled', sprintf('Scheduled %s: %s — %s on %s %s', strtolower($extra->kindLabel()), $course->subject->name, $course->section->label(), $date->format('j M Y'), $data['starts_at']), $extra);

        return redirect()->route('academics.extra.index')->with('success', $extra->kindLabel().' scheduled for '.$extra->start()->format('D j M, H:i').'.'
            .($sms ? " {$sms} student SMS queued in the outbox." : '').($course->teacher_id !== $user->id && $course->teacher ? " {$course->teacher->name} has been alerted." : ''));
    }

    public function cancel(Request $request, ExtraClass $extra, AlertService $alerts): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->isLeadership() || $extra->course->teacher_id === $user->id, 403, 'You can only cancel your own classes.');
        abort_if($extra->cancelled_at || $extra->isPast(), 422, 'This class has already finished or been cancelled.');

        $extra->update(['cancelled_at' => now(), 'cancelled_by' => $user->id]);
        $sms = $extra->students_notified ? $alerts->notifyExtraClass($extra, 'class_cancelled') : 0;
        $this->tellTeacher($extra, 'cancelled', $user);
        ActivityLog::record('timetable.extra_cancelled', sprintf('Cancelled %s: %s on %s', strtolower($extra->kindLabel()), $extra->course->subject->name, $extra->start()->format('j M Y H:i')), $extra);

        return back()->with('success', $extra->kindLabel().' cancelled.'.($sms ? " {$sms} student SMS queued." : ''));
    }

    // ------------------------------------------------------------------

    /** Courses the user may schedule for: own courses, or every course for leadership. */
    private function courses(Request $request)
    {
        $user = $request->user();

        return Course::with(['subject', 'teacher', 'section.program'])
            ->when(! $user->isLeadership(), fn ($q) => $q->where('teacher_id', $user->id))
            ->get()->sortBy(fn ($c) => $c->section->label().$c->subject->name)->values();
    }

    /** When someone else schedules or cancels a teacher's class, the teacher gets an alert. */
    private function tellTeacher(ExtraClass $e, string $what, $by): void
    {
        $teacher = $e->course->teacher;
        if (! $teacher || $teacher->id === $by->id || ! $teacher->is_active) {
            return;
        }
        $teacher->notify(new StaffAlert(
            "extra-{$what}:{$e->id}", 'extra_'.$what,
            $e->kindLabel().' '.$what.': '.$e->course->subject->name,
            $e->course->section->label().' · '.$e->start()->format('D j M, H:i').'–'.$e->end()->format('H:i').' · Room '.$e->room.' · by '.$by->name,
            route('academics.extra.index'),
        ));
    }
}
