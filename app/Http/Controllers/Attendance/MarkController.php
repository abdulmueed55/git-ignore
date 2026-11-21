<?php

namespace App\Http\Controllers\Attendance;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\AttendanceRecord;
use App\Models\Course;
use App\Models\ExtraClass;
use App\Models\LeaveRequest;
use App\Models\Lecture;
use App\Models\Student;
use App\Models\TimetableSlot;
use App\Services\Attendance\AlertService;
use App\Services\Attendance\AttendanceStats;
use App\Services\Attendance\Scope;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class MarkController extends Controller
{
    public function show(Request $request, Course $course, AttendanceStats $stats): View
    {
        abort_unless(Scope::canMark($request->user(), $course), 403, 'This course is not assigned to you.');
        $course->load(['subject', 'teacher', 'section.program', 'section.academicSession']);

        $date = $this->date($request);
        $slots = $course->slots()->where('weekday', $date->dayOfWeekIso)->orderBy('starts_at')->get();
        // An extra / make-up class has no timetable slot: its register is kept by its start time.
        $extra = $request->filled('extra')
            ? ExtraClass::active()->where('course_id', $course->id)->whereDate('held_on', $date->toDateString())->find($request->integer('extra'))
            : null;
        $slot = $extra ? null : ($request->filled('slot') ? $slots->firstWhere('id', (int) $request->input('slot')) : $this->nearestSlot($slots, $date));
        $time = $extra ? substr($extra->starts_at, 0, 5) : ($slot ? substr($slot->starts_at, 0, 5) : ($request->input('time') ?: now()->format('H:00')));

        $lecture = $this->findLecture($course, $date, $slot, $time);
        $students = $this->roster($course);
        $existing = $lecture ? $lecture->records()->get()->keyBy('student_id') : collect();
        $onLeave = $this->onLeave($students->pluck('id')->all(), $date);
        $rates = $stats->studentRates($students->pluck('id')->all(), ['course_ids' => [$course->id]]);
        $overall = $stats->studentRates($students->pluck('id')->all());
        $locked = $lecture?->isLocked() ?? false;
        $readonly = $locked && ! $request->user()->isLeadership();

        $rows = $students->values()->map(fn (Student $s) => [
            'id' => $s->id,
            'name' => $s->name,
            'roll' => $s->roll_no,
            'father' => $s->father_name,
            's' => $existing[$s->id]->status ?? (isset($onLeave[$s->id]) ? 'E' : 'P'),
            'remarks' => $existing[$s->id]->remarks ?? (isset($onLeave[$s->id]) ? 'Approved leave' : ''),
            'leave' => isset($onLeave[$s->id]),
            'rate' => $rates[$s->id]['rate'] ?? null,
            'overall' => $overall[$s->id]['rate'] ?? null,
        ]);

        $recent = $course->lectures()->latest('held_on')->latest('starts_at')->limit(8)->get();

        return view('attendance.mark', compact('course', 'date', 'slots', 'slot', 'extra', 'time', 'lecture', 'rows', 'locked', 'readonly', 'recent'));
    }

    public function store(Request $request, Course $course, AlertService $alerts): RedirectResponse
    {
        $user = $request->user();
        abort_unless(Scope::canMark($user, $course), 403, 'This course is not assigned to you.');

        $data = $request->validate([
            'date' => ['required', 'date', 'before_or_equal:today'],
            'slot' => ['nullable', 'integer'],
            'time' => ['required_without:slot', 'nullable', 'date_format:H:i'],
            'topic' => ['required', 'string', 'min:3', 'max:200'],
            'status' => ['required', 'array'],
            'status.*' => ['required', 'in:P,A,L,E'],
            'remarks' => ['nullable', 'array'],
            'remarks.*' => ['nullable', 'string', 'max:120'],
        ], [
            'topic.required' => 'Write the topic covered — it appears on the register and the student report.',
            'date.before_or_equal' => 'Attendance cannot be marked for a future date.',
        ]);

        $date = Carbon::parse($data['date'])->startOfDay();
        $slot = ! empty($data['slot']) ? TimetableSlot::where('course_id', $course->id)->find($data['slot']) : null;
        if (! empty($data['slot']) && ! $slot) {
            throw ValidationException::withMessages(['slot' => 'That period does not belong to this course.']);
        }
        $time = $slot ? substr($slot->starts_at, 0, 5) : $data['time'];

        // A teacher cannot create a new register for a period whose edit window has already
        // closed (e.g. a slot-less "09:01" next to a locked 09:00 register, or a date months back).
        if (! $user->isLeadership()) {
            $closes = Carbon::parse($date->toDateString().' '.$time)->addHours((int) config('attendance.lock_after_hours'));
            if ($closes->isPast()) {
                return back()->withInput()->with('error', 'Registers can be marked or changed only within '.config('attendance.lock_after_hours').' hours of the period. Ask the principal’s office to record older attendance.');
            }
        }

        $roster = $this->roster($course)->pluck('id')->all();
        $missing = array_diff($roster, array_map('intval', array_keys($data['status'])));
        if ($missing) {
            throw ValidationException::withMessages(['status' => count($missing).' student(s) have no status. Every student on the roll must be marked.']);
        }

        $lecture = $this->findLecture($course, $date, $slot, $time);
        if ($lecture?->isLocked() && ! $user->isLeadership()) {
            return back()->with('error', 'This register is locked. Ask the principal’s office to make changes.');
        }

        [$lecture, $newlyAbsent, $counts] = DB::transaction(function () use ($lecture, $course, $date, $slot, $time, $data, $roster, $user) {
            $lecture ??= new Lecture(['course_id' => $course->id, 'held_on' => $date->toDateString()]);
            $old = $lecture->exists ? $lecture->records()->pluck('status', 'student_id')->all() : [];
            $lecture->fill([
                'timetable_slot_id' => $slot?->id,
                'starts_at' => $slot?->starts_at ?? $time,
                'topic' => $data['topic'],
                'marked_by' => $lecture->marked_by ?? $user->id,
            ]);
            $lecture->locked_at ??= Carbon::parse($date->toDateString().' '.$time)->addHours((int) config('attendance.lock_after_hours'));
            $lecture->save();

            $now = now();
            $rows = [];
            $newlyAbsent = [];
            foreach ($roster as $sid) {
                $status = $data['status'][$sid];
                $rows[] = ['lecture_id' => $lecture->id, 'student_id' => $sid, 'status' => $status,
                    'remarks' => $data['remarks'][$sid] ?? null, 'created_at' => $now, 'updated_at' => $now];
                if ($status === 'A' && ($old[$sid] ?? null) !== 'A') {
                    $newlyAbsent[] = $sid;
                }
            }
            AttendanceRecord::upsert($rows, ['lecture_id', 'student_id'], ['status', 'remarks', 'updated_at']);
            $lecture->refreshCounts();

            return [$lecture, $newlyAbsent, $old ? 'updated' : 'created'];
        });

        $queued = $alerts->afterSave($lecture, $newlyAbsent);

        ActivityLog::record('attendance.'.$counts, sprintf('%s register for %s — %s on %s (%d present, %d absent)',
            ucfirst($counts), $course->subject->name, $course->section->label(), $date->format('j M Y'),
            $lecture->present_count + $lecture->late_count, $lecture->absent_count), $lecture);

        $msg = sprintf('Register saved — %d present, %d absent, %d late, %d on leave.', $lecture->present_count, $lecture->absent_count, $lecture->late_count, $lecture->leave_count)
            .($queued ? " {$queued} guardian alert".($queued > 1 ? 's' : '').' queued.' : '');

        return redirect()->route('attendance.index', ['date' => $date->toDateString()])->with('success', $msg);
    }

    private function roster(Course $course)
    {
        return Student::where('section_id', $course->section_id)->where('status', 'active')->orderBy('roll_no')->get();
    }

    private function findLecture(Course $course, Carbon $date, ?TimetableSlot $slot, string $time): ?Lecture
    {
        $q = Lecture::where('course_id', $course->id)->where('held_on', $date->toDateString());

        return $slot
            ? (clone $q)->where('timetable_slot_id', $slot->id)->first() ?? $q->whereNull('timetable_slot_id')->where('starts_at', 'like', $time.'%')->first()
            : $q->whereNull('timetable_slot_id')->where('starts_at', 'like', $time.'%')->first();
    }

    private function nearestSlot($slots, Carbon $date): ?TimetableSlot
    {
        if ($slots->count() <= 1 || ! $date->isToday()) {
            return $slots->first();
        }
        $now = now()->format('H:i');

        return $slots->filter(fn ($s) => substr($s->starts_at, 0, 5) <= $now)->last() ?? $slots->first();
    }

    /** [student_id => true] for approved leave covering the date. */
    private function onLeave(array $ids, Carbon $date): array
    {
        return LeaveRequest::where('status', 'approved')->whereIn('student_id', $ids ?: [0])
            ->where('from_date', '<=', $date->toDateString())->where('to_date', '>=', $date->toDateString())
            ->pluck('student_id')->flip()->map(fn () => true)->all();
    }

    private function date(Request $request): Carbon
    {
        try {
            $d = $request->filled('date') ? Carbon::parse($request->string('date'))->startOfDay() : today();
        } catch (\Throwable) {
            $d = today();
        }

        return $d->isFuture() ? today() : $d;
    }
}
