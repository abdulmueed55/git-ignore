<?php

namespace Database\Seeders;

use App\Models\Course;
use App\Models\ExtraClass;
use App\Models\User;
use App\Notifications\StaffAlert;
use App\Services\Attendance\AlertService;
use App\Services\Timetable\TeacherSchedule;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/** Extra / make-up / test classes around today, and a few alerts in the demo teacher's bell. */
class ExtraClassSeeder extends Seeder
{
    public function run(): void
    {
        $teacher = User::where('email', 'teacher@fglacollege.edu.pk')->first();
        $principal = User::where('role', 'principal')->first();
        if (! $teacher || ! $principal) {
            return;
        }
        $mine = Course::with(['subject', 'section'])->where('teacher_id', $teacher->id)->get();
        $others = Course::with(['subject', 'section', 'teacher'])->whereNotNull('teacher_id')->where('teacher_id', '!=', $teacher->id)->inRandomOrder()->limit(6)->get();
        if ($mine->isEmpty()) {
            return;
        }
        $alerts = app(AlertService::class);

        // Today, starting soon: the bell alert fires within minutes of opening the demo.
        $soon = now()->addMinutes(25)->ceilMinutes(15);
        $today = $soon->format('H:i') <= '17:30' ? $soon : $this->nextWorkingDay()->setTime(10, 30);
        $first = $this->place($mine[0], $today, 45, 'extra', 'Revision of numericals before the December class test', $teacher);

        // Next working day: a make-up class (teacher), and a class test scheduled by the principal.
        $next = $this->nextWorkingDay();
        $this->place($mine[1 % $mine->count()], $next->copy()->setTime(13, 45), 45, 'makeup', 'Make-up for the period missed on Iqbal Day', $teacher, notify: true, alerts: $alerts);
        $later = $next->copy()->addDays(2);
        while ($later->isWeekend()) {
            $later->addDay();
        }
        $test = $this->place($mine[2 % $mine->count()], $later->setTime(14, 30), 45, 'test', 'Chapter 4 class test — bring calculators', $principal, notify: true, alerts: $alerts);

        // Other teachers' extra classes (seen by the principal).
        foreach ($others->take(4) as $i => $c) {
            $this->place($c, $next->copy()->addDays($i % 3)->setTime(13, 45), 45, $i % 2 ? 'makeup' : 'extra', null, $principal);
        }

        // History: one held last week, one cancelled.
        $past = $this->place($mine[0], now()->subDays(4)->setTime(13, 45), 45, 'extra', 'Lab practice', $teacher, allowPast: true);
        $cancelled = $this->place($mine[1 % $mine->count()], now()->subDays(2)->setTime(14, 30), 45, 'makeup', null, $teacher, allowPast: true);
        $cancelled?->update(['cancelled_at' => now()->subDays(3), 'cancelled_by' => $teacher->id]);

        // The demo teacher's bell.
        if ($test) {
            $teacher->notify(new StaffAlert("extra-scheduled:{$test->id}", 'extra_scheduled', 'Class test scheduled: '.$test->course->subject->name,
                $test->course->section->label().' · '.$test->start()->format('D j M, H:i').'–'.$test->end()->format('H:i').' · Room '.$test->room.' · by '.$principal->name,
                route('academics.extra.index')));
        }
        $yesterday = TeacherSchedule::forTeacher($teacher->id, now()->subDay());
        if ($yesterday->isNotEmpty()) {
            $n = $teacher->notifications()->create([
                'id' => (string) Str::uuid(), 'type' => StaffAlert::class,
                'data' => ['key' => 'day:'.now()->subDay()->toDateString(), 'kind' => 'daily', 'title' => 'Yesterday: '.$yesterday->count().' classes',
                    'body' => $yesterday->map(fn ($e) => $e->start->format('H:i').' '.$e->course->subject->name)->implode(' · '), 'url' => route('attendance.index')],
                'read_at' => now()->subDay()->setTime(8, 5),
            ]);
            $n->forceFill(['created_at' => now()->subDay()->setTime(7, 30)])->save();
        }
        unset($first, $past);
    }

    private function nextWorkingDay(): Carbon
    {
        $d = today()->addDay();
        while ($d->isWeekend() || isset(config('attendance.holidays')[$d->toDateString()])) {
            $d->addDay();
        }

        return $d;
    }

    /** Creates the class at the first free time from $at (moving in 45-minute steps). */
    private function place(Course $course, Carbon $at, int $minutes, string $kind, ?string $reason, User $by, bool $notify = false, ?AlertService $alerts = null, bool $allowPast = false): ?ExtraClass
    {
        for ($try = 0; $try < 8; $try++, $at->addMinutes(45)) {
            $start = $at->format('H:i');
            $end = $at->copy()->addMinutes($minutes)->format('H:i');
            if ($end > '18:30' || (! $allowPast && $at->isPast())) {
                continue;
            }
            if (TeacherSchedule::clash($course, $at, $start, $end)) {
                continue;
            }
            $e = ExtraClass::create([
                'course_id' => $course->id, 'held_on' => $at->toDateString(), 'starts_at' => $start, 'ends_at' => $end,
                'room' => $kind === 'test' ? 'Examination Hall' : ($course->section->room ?: 'B-2'), 'kind' => $kind, 'reason' => $reason, 'created_by' => $by->id,
            ]);
            $e->forceFill(['created_at' => now()->subDays(1), 'updated_at' => now()->subDays(1)])->saveQuietly();
            if ($notify && $alerts) {
                $alerts->notifyExtraClass($e);
                $e->update(['students_notified' => true]);
            }

            return $e;
        }

        return null;
    }
}
