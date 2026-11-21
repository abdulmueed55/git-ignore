<?php

namespace App\Services\Timetable;

use App\Models\Course;
use App\Models\ExtraClass;
use App\Models\TimetableSlot;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * One day's classes: the weekly timetable periods plus any extra / make-up classes
 * scheduled for that date. Used by the attendance day view, the dashboard, the
 * reminders and the notification bell, so every screen agrees on "your next class".
 */
class TeacherSchedule
{
    /**
     * @param  array<int>|null  $courseIds  null = every course (leadership)
     * @return Collection<int, object>
     */
    public static function day(Carbon $date, ?array $courseIds = null): Collection
    {
        $d = $date->toDateString();
        $holiday = config('attendance.holidays')[$d] ?? null;

        $regular = $holiday ? collect() : TimetableSlot::query()
            ->where('weekday', $date->dayOfWeekIso)
            ->when($courseIds !== null, fn ($q) => $q->whereIn('course_id', $courseIds ?: [0]))
            ->whereHas('course.section.academicSession', fn ($q) => $q->where('starts_on', '<=', $d))
            ->with(['course.subject', 'course.teacher', 'course.section.program'])
            ->get()
            ->map(fn (TimetableSlot $s) => (object) [
                'type' => 'regular',
                'label' => 'Class',
                'course' => $s->course,
                'slot' => $s,
                'extra' => null,
                'start' => Carbon::parse($d.' '.$s->starts_at),
                'end' => Carbon::parse($d.' '.$s->ends_at),
                'room' => $s->room ?: $s->course->section->room,
                'key' => "slot:{$s->id}:{$d}",
                'markUrl' => route('attendance.mark', ['course' => $s->course_id, 'date' => $d, 'slot' => $s->id]),
            ]);

        $extra = ExtraClass::active()->whereDate('held_on', $d)
            ->when($courseIds !== null, fn ($q) => $q->whereIn('course_id', $courseIds ?: [0]))
            ->with(['course.subject', 'course.teacher', 'course.section.program'])
            ->get()
            ->map(fn (ExtraClass $e) => (object) [
                'type' => 'extra',
                'label' => $e->kindLabel(),
                'course' => $e->course,
                'slot' => null,
                'extra' => $e,
                'start' => $e->start(),
                'end' => $e->end(),
                'room' => $e->room,
                'key' => "extra:{$e->id}",
                'markUrl' => route('attendance.mark', ['course' => $e->course_id, 'date' => $d, 'time' => substr($e->starts_at, 0, 5), 'extra' => $e->id]),
            ]);

        return $regular->concat($extra)->sortBy(fn ($i) => $i->start->format('H:i').$i->course->id)->values();
    }

    /** Classes of one teacher on a date. */
    public static function forTeacher(int $teacherId, Carbon $date): Collection
    {
        return self::day($date, Course::where('teacher_id', $teacherId)->pluck('id')->all());
    }

    /**
     * Why a one-off class cannot go at this time, or null when it can: the teacher
     * or the class (section) is already busy with a regular period or another extra class.
     */
    public static function clash(Course $course, Carbon $date, string $start, string $end, ?int $ignoreExtra = null): ?string
    {
        $overlaps = fn ($q) => $q->where('starts_at', '<', $end)->where('ends_at', '>', $start);
        $d = $date->toDateString();

        $checks = [
            'teacher' => fn ($q) => $q->where('teacher_id', $course->teacher_id),
            'section' => fn ($q) => $q->where('section_id', $course->section_id),
        ];
        foreach ($checks as $who => $scope) {
            if ($who === 'teacher' && ! $course->teacher_id) {
                continue;
            }
            $slot = isset(config('attendance.holidays')[$d]) ? null : TimetableSlot::where('weekday', $date->dayOfWeekIso)->whereHas('course', $scope)
                ->where($overlaps)->with('course.subject')->first();
            $other = ExtraClass::active()->whereDate('held_on', $d)->whereHas('course', $scope)->where($overlaps)
                ->when($ignoreExtra, fn ($q) => $q->whereKeyNot($ignoreExtra))->with('course.subject')->first();
            $busy = $slot ?? $other;
            if ($busy) {
                $what = $busy->course->subject->name.' '.substr($busy->starts_at, 0, 5).'–'.substr($busy->ends_at, 0, 5);

                return $who === 'teacher'
                    ? "The teacher already has {$what} at that time."
                    : "This class already has {$what} at that time.";
            }
        }

        return null;
    }
}
