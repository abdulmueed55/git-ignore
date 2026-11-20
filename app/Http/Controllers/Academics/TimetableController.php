<?php

namespace App\Http\Controllers\Academics;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Course;
use App\Models\Section;
use App\Models\Subject;
use App\Models\TimetableSlot;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class TimetableController extends Controller
{
    /** Standard college day. A 15-minute break follows the third period. */
    public const PERIODS = [
        ['08:00', '08:45'], ['08:45', '09:30'], ['09:30', '10:15'],
        ['10:30', '11:15'], ['11:15', '12:00'], ['12:00', '12:45'], ['12:45', '13:30'],
    ];

    public const BREAK = ['10:15', '10:30'];

    public const DAYS = [1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday'];

    private const DEPT_COLORS = [
        'ENG' => '#2f6fae', 'URD' => '#8a5a9e', 'ISL' => '#1f8a5b', 'PST' => '#5d7a3a', 'MTH' => '#c0573a',
        'PHY' => '#2b8a9a', 'CHE' => '#b8862b', 'BIO' => '#4f9a3c', 'CS' => '#5b5fc7', 'ECO' => '#a0662a',
        'COM' => '#9a4a6a', 'STA' => '#6c7a89', 'POL' => '#7a5a3a',
    ];

    public static function deptColor(?string $code): string
    {
        if ($code && isset(self::DEPT_COLORS[$code])) {
            return self::DEPT_COLORS[$code];
        }

        return sprintf('hsl(%d 45%% 45%%)', crc32((string) $code) % 360);
    }

    public function index(Request $request): View
    {
        $user = $request->user();
        $leader = $user->isLeadership();

        $mySectionIds = Course::where('teacher_id', $user->id)->pluck('section_id')->unique();

        // Which view? section (?section=) or teacher (?teacher=). Teachers default to their own.
        $section = $request->filled('section') ? Section::with(['program', 'academicSession'])->findOrFail($request->integer('section')) : null;
        $teacher = $request->filled('teacher') ? User::findOrFail($request->integer('teacher')) : null;

        if (! $leader) {
            if ($section && ! $mySectionIds->contains($section->id)) {
                abort(403, 'You can only view timetables of sections you teach.');
            }
            if ($teacher && $teacher->id !== $user->id) {
                abort(403, 'You can only view your own teaching timetable.');
            }
            if (! $section && ! $teacher) {
                $teacher = $user;
            }
        } elseif (! $section && ! $teacher) {
            $section = Section::with(['program', 'academicSession'])->orderBy('program_id')->orderBy('year')->first();
        }

        $slots = TimetableSlot::with(['course.subject.department', 'course.teacher', 'course.section.program'])
            ->whereHas('course', fn ($q) => $section ? $q->where('section_id', $section->id) : $q->where('teacher_id', $teacher?->id))
            ->get();

        $grid = [];
        foreach ($slots as $slot) {
            $grid[$slot->weekday][substr($slot->starts_at, 0, 5)][] = $slot;
        }

        $periods = collect(self::PERIODS);
        foreach ($slots as $slot) {
            $start = substr($slot->starts_at, 0, 5);
            if (! $periods->contains(fn ($p) => $p[0] === $start)) {
                $periods->push([$start, substr($slot->ends_at, 0, 5)]);
            }
        }
        $periods = $periods->sortBy(0)->values();

        $sectionOptions = Section::with('program')->when(! $leader, fn ($q) => $q->whereIn('id', $mySectionIds))
            ->orderBy('program_id')->orderBy('year')->get()
            ->mapWithKeys(fn ($s) => [$s->id => $s->label()]);

        $workload = $this->workload();

        return view('academics.timetable.index', [
            'section' => $section,
            'teacher' => $teacher,
            'grid' => $grid,
            'periods' => $periods,
            'slots' => $slots,
            'leader' => $leader,
            'sectionOptions' => $sectionOptions,
            'teacherOptions' => $leader ? User::where('role', 'teacher')->orderBy('name')->pluck('name', 'id') : collect([$user->id => $user->name]),
            'courses' => $section ? $section->courses()->with(['subject.department', 'teacher'])->get() : collect(),
            'subjects' => Subject::orderBy('name')->get(),
            'allTeachers' => User::where('role', 'teacher')->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'workload' => $workload,
            'myLoad' => $teacher ? ($workload->firstWhere('id', $teacher->id)['periods'] ?? 0) : null,
        ]);
    }

    public function storeSlot(Request $request): RedirectResponse
    {
        $data = $this->validateSlot($request);
        $course = Course::with(['subject', 'section.program'])->findOrFail($data['course_id']);
        $this->assertNoClash($course, $data['weekday'], $data['starts_at'], $data['ends_at']);

        $slot = TimetableSlot::create($data);
        ActivityLog::record('academics.slot_added', "Scheduled {$course->subject->name} for {$course->section->label()} on ".self::DAYS[$data['weekday']]." at {$data['starts_at']}", $slot);

        return back()->with('success', 'Period added to the timetable.');
    }

    public function updateSlot(Request $request, TimetableSlot $slot): RedirectResponse
    {
        $data = $this->validateSlot($request);
        $course = Course::with(['subject', 'section.program'])->findOrFail($data['course_id']);
        $this->assertNoClash($course, $data['weekday'], $data['starts_at'], $data['ends_at'], $slot->id);

        $slot->update($data);
        ActivityLog::record('academics.slot_moved', "Moved {$course->subject->name} ({$course->section->label()}) to ".self::DAYS[$data['weekday']]." {$data['starts_at']}", $slot);

        return back()->with('success', 'Period updated.');
    }

    public function destroySlot(TimetableSlot $slot): RedirectResponse
    {
        $slot->load('course.subject', 'course.section.program');
        ActivityLog::record('academics.slot_removed', "Removed {$slot->course->subject->name} period for {$slot->course->section->label()} on ".(self::DAYS[$slot->weekday] ?? 'day '.$slot->weekday));
        $slot->delete();

        return back()->with('success', 'Period removed.');
    }

    public function storeCourse(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'section_id' => ['required', 'exists:sections,id'],
            'subject_id' => ['required', 'exists:subjects,id', Rule::unique('courses')->where('section_id', $request->integer('section_id'))],
            'teacher_id' => ['required', Rule::exists('users', 'id')->where('role', 'teacher')],
        ], ['subject_id.unique' => 'This subject is already taught in this section.']);

        $course = Course::create($data)->load('subject', 'section.program', 'teacher');
        ActivityLog::record('academics.course_added', "Added {$course->subject->name} to {$course->section->label()} with {$course->teacher->name}", $course);

        return back()->with('success', "{$course->subject->name} added. Now schedule its periods.");
    }

    public function assignTeacher(Request $request, Course $course): RedirectResponse
    {
        $data = $request->validate(['teacher_id' => ['required', Rule::exists('users', 'id')->where('role', 'teacher')]]);
        $course->load('slots', 'subject', 'section.program');
        $teacher = User::find($data['teacher_id']);

        foreach ($course->slots as $slot) {
            $clash = $this->teacherClash($teacher->id, $slot->weekday, $slot->starts_at, $slot->ends_at, $course->id);
            if ($clash) {
                throw ValidationException::withMessages([
                    'teacher_id' => "{$teacher->name} already teaches {$clash->course->subject->name} ({$clash->course->section->label()}) on ".self::DAYS[$slot->weekday].' at '.substr($clash->starts_at, 0, 5).'.',
                ]);
            }
        }

        $old = $course->teacher?->name;
        $course->update(['teacher_id' => $teacher->id]);
        ActivityLog::record('academics.teacher_assigned', "Assigned {$teacher->name} to {$course->subject->name} ({$course->section->label()})".($old ? ", replacing {$old}" : ''), $course);

        return back()->with('success', "{$teacher->name} now teaches {$course->subject->name}.");
    }

    // ------------------------------------------------------------------

    private function validateSlot(Request $request): array
    {
        $data = $request->validate([
            'course_id' => ['required', 'exists:courses,id'],
            'weekday' => ['required', 'integer', Rule::in(array_keys(self::DAYS))],
            'starts_at' => ['required', 'date_format:H:i'],
            'ends_at' => ['required', 'date_format:H:i', 'after:starts_at'],
            'room' => ['required', 'string', 'max:32'],
        ], ['ends_at.after' => 'The period must end after it starts.']);
        $data['weekday'] = (int) $data['weekday'];

        return $data;
    }

    private function assertNoClash(Course $course, int $day, string $start, string $end, ?int $ignoreSlot = null): void
    {
        $sectionClash = TimetableSlot::with('course.subject')
            ->where('weekday', $day)
            ->when($ignoreSlot, fn ($q) => $q->where('id', '!=', $ignoreSlot))
            ->whereHas('course', fn ($q) => $q->where('section_id', $course->section_id))
            ->where('starts_at', '<', $end)->where('ends_at', '>', $start)
            ->first();
        if ($sectionClash) {
            throw ValidationException::withMessages([
                'starts_at' => "{$course->section->label()} already has {$sectionClash->course->subject->name} on ".self::DAYS[$day].' from '.substr($sectionClash->starts_at, 0, 5).' to '.substr($sectionClash->ends_at, 0, 5).'.',
            ]);
        }

        if ($course->teacher_id && ($clash = $this->teacherClash($course->teacher_id, $day, $start, $end, null, $ignoreSlot))) {
            throw ValidationException::withMessages([
                'starts_at' => ($course->teacher?->name ?? 'The teacher')." is already teaching {$clash->course->subject->name} to {$clash->course->section->label()} on ".self::DAYS[$day].' at '.substr($clash->starts_at, 0, 5).'.',
            ]);
        }
    }

    private function teacherClash(int $teacherId, int $day, string $start, string $end, ?int $ignoreCourse = null, ?int $ignoreSlot = null): ?TimetableSlot
    {
        $start = substr($start, 0, 5);
        $end = substr($end, 0, 5);

        return TimetableSlot::with('course.subject', 'course.section.program')
            ->where('weekday', $day)
            ->when($ignoreSlot, fn ($q) => $q->where('id', '!=', $ignoreSlot))
            ->whereHas('course', fn ($q) => $q->where('teacher_id', $teacherId)->when($ignoreCourse, fn ($w) => $w->where('id', '!=', $ignoreCourse)))
            ->where('starts_at', '<', $end)->where('ends_at', '>', $start)
            ->first();
    }

    /** @return Collection<int, array{id:int,name:string,periods:int,courses:int}> */
    private function workload(): Collection
    {
        $periods = TimetableSlot::join('courses', 'courses.id', '=', 'timetable_slots.course_id')
            ->selectRaw('courses.teacher_id, count(*) as c')->groupBy('courses.teacher_id')->pluck('c', 'teacher_id');

        return User::where('role', 'teacher')
            ->withCount('courses')
            ->get(['id', 'name'])
            ->map(fn ($t) => [
                'id' => $t->id,
                'name' => $t->name,
                'courses' => $t->courses_count,
                'periods' => (int) ($periods[$t->id] ?? 0),
            ])
            ->sortByDesc('periods')->values();
    }
}
