<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\AttendanceRecord;
use App\Models\ExtraClass;
use App\Models\LeaveRequest;
use App\Models\MeritListEntry;
use App\Models\Post;
use App\Services\Student\StudentPortal;
use App\Services\Timetable\TeacherSchedule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

/** The signed-in student's own pages. Every query is scoped to request()->user('student'). */
class PortalController extends Controller
{
    public function __construct(private StudentPortal $portal) {}

    public function home(Request $request): View
    {
        $s = $request->user('student')->load(['program', 'section.program', 'academicSession']);
        $courses = $this->portal->courses($s);
        $ids = $courses->pluck('id')->all();

        // Today's classes with this student's mark where the register is already taken.
        $marks = AttendanceRecord::where('student_id', $s->id)
            ->join('lectures', 'lectures.id', '=', 'attendance_records.lecture_id')
            ->whereDate('lectures.held_on', today())
            ->get(['lectures.course_id', 'lectures.starts_at', 'attendance_records.status'])
            ->mapWithKeys(fn ($r) => [$r->course_id.'@'.substr((string) $r->starts_at, 0, 5) => $r->status]);

        return view('student.home', [
            'student' => $s,
            'overall' => $this->portal->overall($s),
            'rates' => $this->portal->rates($s),
            'courses' => $courses,
            'today' => TeacherSchedule::day(today(), $ids),
            'marks' => $marks,
            'upcoming' => ExtraClass::with('course.subject')->active()->whereIn('course_id', $ids ?: [0])
                ->whereDate('held_on', '>=', today())->orderBy('held_on')->orderBy('starts_at')->limit(5)->get(),
            'notices' => Post::live()->whereIn('type', ['notice', 'announcement'])->orderByDesc('is_pinned')->latest('published_at')->limit(5)->get(),
            'pendingLeave' => LeaveRequest::where('student_id', $s->id)->where('status', 'pending')->count(),
            'threshold' => (float) config('attendance.threshold'),
        ]);
    }

    public function attendance(Request $request): View
    {
        $s = $request->user('student');
        $threshold = (float) config('attendance.threshold');
        $rates = $this->portal->rates($s);
        $subjects = $this->portal->courses($s)->map(function ($c) use ($rates, $threshold) {
            $r = $rates[$c->id] ?? ['P' => 0, 'A' => 0, 'L' => 0, 'E' => 0, 'counted' => 0, 'rate' => null];
            $attended = $r['P'] + $r['L'];
            // Classes in a row needed to get back to the threshold: (a + x) / (c + x) >= t.
            $need = $r['rate'] !== null && $r['rate'] < $threshold ? (int) ceil(($threshold / 100 * $r['counted'] - $attended) / (1 - $threshold / 100)) : 0;

            return ['course' => $c, 'r' => $r, 'need' => $need];
        });

        return view('student.attendance', [
            'student' => $s,
            'overall' => $this->portal->overall($s),
            'subjects' => $subjects,
            'recent' => $this->portal->recent($s, 40),
            'threshold' => $threshold,
        ]);
    }

    public function timetable(Request $request): View
    {
        $s = $request->user('student')->load('section.program');
        $ids = $this->portal->courses($s)->pluck('id')->all();
        $days = collect(range(0, 6))->map(fn ($i) => today()->addDays($i))->reject->isSunday()
            ->map(fn ($d) => ['date' => $d, 'holiday' => config('attendance.holidays')[$d->toDateString()] ?? null, 'items' => TeacherSchedule::day($d, $ids)]);

        return view('student.timetable', ['student' => $s, 'days' => $days]);
    }

    public function application(Request $request): View
    {
        $s = $request->user('student');
        $app = $s->application()->with(['program', 'cycle', 'documents'])->first();
        $entry = $app ? MeritListEntry::with('meritList.program')->where('admission_application_id', $app->id)
            ->whereHas('meritList', fn ($q) => $q->where('status', 'published'))->latest()->first() : null;

        return view('student.application', ['student' => $s, 'app' => $app, 'entry' => $entry]);
    }

    /** The student's own uploaded admission document (private disk, sandboxed). */
    public function file(Request $request, string $type)
    {
        $app = $request->user('student')->application;
        abort_unless($app, 404);
        $doc = $app->documents()->where('type', $type)->firstOrFail();
        abort_unless(Storage::disk('local')->exists($doc->path), 404);

        return Storage::disk('local')->response($doc->path, null, [
            'Content-Type' => $doc->mime, 'Cache-Control' => 'private, max-age=300', 'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "sandbox; default-src 'none'; img-src 'self' data:; style-src 'unsafe-inline'",
        ]);
    }
}
