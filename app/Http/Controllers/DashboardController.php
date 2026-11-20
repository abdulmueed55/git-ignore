<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\AdmissionApplication;
use App\Models\AdmissionCycle;
use App\Models\Course;
use App\Models\Lecture;
use App\Models\Post;
use App\Models\Program;
use App\Models\Student;
use App\Models\TimetableSlot;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The "Command centre": one screen that answers "what needs attention right now?"
 * Content adapts to the signed-in role (leadership / admission officer / teacher).
 */
class DashboardController extends Controller
{
    public function __invoke(Request $request)
    {
        $user = $request->user();

        if ($user->role === 'editor') {
            return redirect()->route('studio.dashboard');
        }

        $today = today();
        $leadership = $user->isLeadership();
        $isTeacher = $user->role === 'teacher';
        $isAdmissions = $user->role === 'admission_officer';

        // Teachers see their own courses; leadership sees the whole college.
        $courseIds = $isTeacher ? $user->courses()->pluck('id') : null;
        $sectionIds = $isTeacher ? $user->courses()->pluck('section_id')->unique() : null;

        $data = [
            'user' => $user,
            'leadership' => $leadership,
            'isTeacher' => $isTeacher,
            'isAdmissions' => $isAdmissions,
            'threshold' => (int) config('attendance.threshold', 75),
        ];

        if (! $isAdmissions) {
            $data += $this->attendance($today, $courseIds, $sectionIds);
            $data['schedule'] = $this->todaysSchedule($today, $courseIds);
        }

        if ($leadership || $isAdmissions) {
            $data += $this->admissions();
        }

        if ($leadership) {
            $data['enrolment'] = Program::query()
                ->withCount([
                    'students as enrolled' => fn ($q) => $q->where('status', 'active'),
                    'sections as batches' => fn ($q) => $q->select(DB::raw('COUNT(DISTINCT academic_session_id)')),
                ])
                ->orderBy('level')->orderBy('code')->get(['id', 'code', 'name', 'level', 'seats']);
            $data['cms'] = $this->cms();
            $data['activity'] = ActivityLog::with('user:id,name')->latest()->limit(7)->get();
        }

        $data['studentsActive'] = Student::where('status', 'active')
            ->when($sectionIds, fn ($q) => $q->whereIn('section_id', $sectionIds))->count();

        $data['headline'] = $this->headline($data);

        return view('dashboard', $data);
    }

    private function attendance(Carbon $today, ?Collection $courseIds, ?Collection $sectionIds): array
    {
        $lectures = Lecture::query()->when($courseIds, fn ($q) => $q->whereIn('course_id', $courseIds));

        $sum = 'SUM(present_count + late_count) as present, SUM(present_count + absent_count + late_count + leave_count) as total';

        $todayRow = (clone $lectures)->whereDate('held_on', $today)->selectRaw($sum.', COUNT(*) as registers')->first();
        $todayRate = $todayRow && $todayRow->total ? round($todayRow->present / $todayRow->total * 100, 1) : null;

        $from = $today->copy()->subDays(41);
        $trend = (clone $lectures)->whereBetween('held_on', [$from->toDateString(), $today->toDateString()])
            ->selectRaw('held_on, '.$sum)->groupBy('held_on')->orderBy('held_on')->get()
            ->map(fn ($r) => ['date' => Carbon::parse($r->held_on), 'rate' => $r->total ? round($r->present / $r->total * 100, 1) : 0])
            ->values();

        $last7 = $trend->filter(fn ($r) => $r['date']->gte($today->copy()->subDays(7)) && ! $r['date']->isSameDay($today))->avg('rate');
        $prev7 = $trend->filter(fn ($r) => $r['date']->between($today->copy()->subDays(14), $today->copy()->subDays(8)))->avg('rate');

        $byProgram = DB::table('lectures')
            ->join('courses', 'courses.id', '=', 'lectures.course_id')
            ->join('sections', 'sections.id', '=', 'courses.section_id')
            ->join('programs', 'programs.id', '=', 'sections.program_id')
            ->when($courseIds, fn ($q) => $q->whereIn('lectures.course_id', $courseIds))
            ->where('lectures.held_on', '>=', $today->copy()->subDays(30)->toDateString())
            ->selectRaw('programs.code, SUM(lectures.present_count + lectures.late_count) as present, SUM(lectures.present_count + lectures.absent_count + lectures.late_count + lectures.leave_count) as total')
            ->groupBy('programs.code')->orderBy('programs.code')->get()
            ->map(fn ($r) => ['code' => $r->code, 'rate' => $r->total ? round($r->present / $r->total * 100, 1) : 0]);

        // Students below the attendance threshold (overall, this term)
        $threshold = (int) config('attendance.threshold', 75);
        $perStudent = DB::table('attendance_records')
            ->join('students', 'students.id', '=', 'attendance_records.student_id')
            ->where('students.status', 'active')
            ->when($sectionIds, fn ($q) => $q->whereIn('students.section_id', $sectionIds))
            ->selectRaw("attendance_records.student_id, SUM(CASE WHEN attendance_records.status IN ('P','L') THEN 1 ELSE 0 END) as present, COUNT(*) as total")
            ->groupBy('attendance_records.student_id')
            ->havingRaw('COUNT(*) >= 10')
            ->get();
        $defaulterRows = $perStudent->map(fn ($r) => ['id' => $r->student_id, 'rate' => round($r->present / $r->total * 100, 1)])
            ->filter(fn ($r) => $r['rate'] < $threshold)->sortBy('rate')->values();
        $defaulters = Student::with('section.program:id,code')->whereIn('id', $defaulterRows->take(6)->pluck('id'))->get()
            ->map(fn ($s) => ['student' => $s, 'rate' => $defaulterRows->firstWhere('id', $s->id)['rate']])
            ->sortBy('rate')->values();

        return [
            'todayRate' => $todayRate,
            'todayPresent' => (int) ($todayRow->present ?? 0),
            'todayTotal' => (int) ($todayRow->total ?? 0),
            'registersMarked' => (int) ($todayRow->registers ?? 0),
            'trend' => $trend,
            'weekRate' => $last7 ? round($last7, 1) : null,
            'weekDelta' => ($last7 && $prev7) ? round($last7 - $prev7, 1) : null,
            'byProgram' => $byProgram,
            'defaulterCount' => $defaulterRows->count(),
            'defaulters' => $defaulters,
            'termRate' => $perStudent->sum('total') ? round($perStudent->sum('present') / $perStudent->sum('total') * 100, 1) : null,
        ];
    }

    /** Today's timetable with marking state: done / due (start time passed) / upcoming. */
    private function todaysSchedule(Carbon $today, ?Collection $courseIds): Collection
    {
        $weekday = $today->dayOfWeekIso;
        if ($weekday > 6) {
            return collect();
        }

        $slots = TimetableSlot::with(['course.subject:id,name,code', 'course.section.program:id,code', 'course.teacher:id,name'])
            ->where('weekday', $weekday)
            ->when($courseIds, fn ($q) => $q->whereIn('course_id', $courseIds))
            ->orderBy('starts_at')->get();

        $marked = Lecture::whereDate('held_on', $today)->whereIn('course_id', $slots->pluck('course_id'))
            ->get(['id', 'course_id', 'starts_at', 'present_count', 'absent_count', 'late_count', 'leave_count'])
            ->keyBy(fn ($l) => $l->course_id.'@'.substr((string) $l->starts_at, 0, 5));

        $now = now()->format('H:i');

        return $slots->map(function ($slot) use ($marked, $now) {
            $start = substr($slot->starts_at, 0, 5);
            $lecture = $marked->get($slot->course_id.'@'.$start);

            return [
                'slot' => $slot,
                'start' => $start,
                'end' => substr($slot->ends_at, 0, 5),
                'lecture' => $lecture,
                'state' => $lecture ? 'done' : ($start <= $now ? 'due' : 'upcoming'),
            ];
        });
    }

    private function admissions(): array
    {
        $cycle = AdmissionCycle::active() ?? AdmissionCycle::latest('opens_at')->first();
        if (! $cycle) {
            return ['cycle' => null];
        }

        $apps = AdmissionApplication::where('admission_cycle_id', $cycle->id);
        $byStatus = (clone $apps)->selectRaw('status, COUNT(*) c')->groupBy('status')->pluck('c', 'status');
        $submitted = $byStatus->except('draft')->sum();

        $funnel = [
            ['label' => 'Started', 'value' => $byStatus->sum(), 'tone' => 'muted'],
            ['label' => 'Submitted', 'value' => $submitted, 'tone' => 'brass'],
            ['label' => 'Verified', 'value' => $byStatus->only(['verified', 'selected', 'admitted', 'waitlisted'])->sum(), 'tone' => 'info'],
            ['label' => 'Selected', 'value' => $byStatus->only(['selected', 'admitted'])->sum(), 'tone' => 'brand'],
            ['label' => 'Admitted', 'value' => (int) ($byStatus['admitted'] ?? 0), 'tone' => 'ok'],
        ];

        $flagged = (clone $apps)->with('program:id,code')
            ->whereIn('verification_status', ['review', 'mismatch'])
            ->whereNotIn('status', ['admitted', 'rejected', 'draft'])
            ->with(['checks' => fn ($q) => $q->whereIn('result', ['mismatch', 'partial', 'missing'])->where('overridden', false)])
            ->orderByRaw("CASE verification_status WHEN 'mismatch' THEN 0 ELSE 1 END")
            ->orderBy('verification_score')->limit(5)->get();

        $verifiedPool = (clone $apps)->whereNotNull('verification_score')->where('status', '!=', 'draft');
        $autoClear = (clone $verifiedPool)->count()
            ? round((clone $verifiedPool)->where('verification_status', 'clear')->count() / (clone $verifiedPool)->count() * 100)
            : null;

        $demand = Program::query()->where('is_active', true)
            ->withCount(['applications as applied' => fn ($q) => $q->where('admission_cycle_id', $cycle->id)->where('status', '!=', 'draft')])
            ->get(['id', 'code', 'seats'])->filter(fn ($p) => $p->applied > 0)->sortByDesc('applied')->values();

        $daily = (clone $apps)->whereNotNull('submitted_at')->where('submitted_at', '>=', now()->subDays(20))
            ->selectRaw('DATE(submitted_at) d, COUNT(*) c')->groupBy('d')->pluck('c', 'd');

        return [
            'cycle' => $cycle,
            'funnel' => $funnel,
            'flagged' => $flagged,
            'flaggedCount' => (clone $apps)->whereIn('verification_status', ['review', 'mismatch'])->whereNotIn('status', ['admitted', 'rejected', 'draft'])->count(),
            'autoClear' => $autoClear,
            'awaitingReview' => (int) (($byStatus['submitted'] ?? 0) + ($byStatus['under_review'] ?? 0)),
            'demand' => $demand,
            'daily' => collect(range(19, 0))->mapWithKeys(fn ($i) => [now()->subDays($i)->format('j M') => (int) ($daily[now()->subDays($i)->toDateString()] ?? 0)]),
        ];
    }

    private function cms(): array
    {
        return [
            'live' => Post::live()->count(),
            'scheduled' => Post::where('status', 'scheduled')->orWhere(fn ($q) => $q->where('status', 'published')->where('published_at', '>', now()))->count(),
            'events' => Post::live()->where('type', 'event')->where('event_starts_at', '>=', now()->startOfDay())->orderBy('event_starts_at')->limit(3)->get(),
            'latest' => Post::live()->whereIn('type', ['announcement', 'notice'])->orderByDesc('is_pinned')->latest('published_at')->limit(4)->get(),
        ];
    }

    /** One sentence summary, written like a registrar's note. */
    private function headline(array $d): string
    {
        $bits = [];
        if (isset($d['todayTotal']) && $d['todayTotal'] > 0) {
            $bits[] = number_format($d['todayPresent']).' of '.number_format($d['todayTotal']).' student-lectures attended so far today';
        }
        if (isset($d['schedule'])) {
            $due = $d['schedule']->where('state', 'due')->count();
            if ($due) {
                $bits[] = $due.' '.str('register')->plural($due).' still to be marked';
            }
        }
        if (! empty($d['flaggedCount'])) {
            $bits[] = $d['flaggedCount'].' '.str('application')->plural($d['flaggedCount']).' flagged by document checks';
        }
        if (! empty($d['defaulterCount'])) {
            $bits[] = $d['defaulterCount'].' students below '.$d['threshold'].'% attendance';
        }

        return $bits ? ucfirst(implode(', ', array_slice($bits, 0, -1)).(count($bits) > 1 ? ' and ' : '').end($bits)).'.' : 'Everything is up to date.';
    }
}
