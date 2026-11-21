<?php

namespace App\Services\Timetable;

use App\Models\Course;
use App\Models\User;
use App\Notifications\StaffAlert;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Alerts teachers about their classes: the day's list in the morning and each class a
 * few minutes before it starts (regular periods and extra classes alike). Safe to run
 * as often as you like — every alert has a unique key and is sent once.
 */
class ClassReminders
{
    /** @return int alerts sent */
    public function run(?Carbon $now = null, ?User $only = null): int
    {
        $now ??= now();
        $courseIds = $only ? Course::where('teacher_id', $only->id)->pluck('id')->all() : null;
        if ($only && ! $courseIds) {
            return 0;
        }
        $entries = TeacherSchedule::day($now->copy()->startOfDay(), $courseIds)
            ->filter(fn ($e) => $e->course->teacher && $e->course->teacher->is_active);

        return $this->soon($entries, $now) + $this->daily($entries, $now);
    }

    private function soon(Collection $entries, Carbon $now): int
    {
        $lead = (int) config('timetable.reminder_minutes', 10) * 60;
        $sent = 0;
        foreach ($entries as $e) {
            $seconds = $now->diffInSeconds($e->start, false);
            if ($seconds <= 0 || $seconds > $lead) {
                continue;
            }
            $teacher = $e->course->teacher;
            $key = 'soon:'.$e->key;
            if ($this->already($teacher, $key)) {
                continue;
            }
            $mins = max(1, (int) ceil($seconds / 60));
            $teacher->notify(new StaffAlert(
                $key, 'class_soon',
                ($e->type === 'extra' ? $e->label.': ' : '').$e->course->subject->name." in {$mins} min",
                $e->course->section->label().' · Room '.$e->room.' · '.$e->start->format('H:i').'–'.$e->end->format('H:i'),
                $e->markUrl,
                (bool) config('timetable.sms_reminders'),
            ));
            $sent++;
        }

        return $sent;
    }

    private function daily(Collection $entries, Carbon $now): int
    {
        if ($now->format('H:i') < (string) config('timetable.daily_summary_at', '07:30')) {
            return 0;
        }
        $sent = 0;
        foreach ($entries->groupBy(fn ($e) => $e->course->teacher_id) as $list) {
            $teacher = $list->first()->course->teacher;
            $key = 'day:'.$now->toDateString();
            if ($list->every(fn ($e) => $e->end->lte($now)) || $this->already($teacher, $key)) {
                continue;
            }
            $extra = $list->where('type', 'extra')->count();
            $teacher->notify(new StaffAlert(
                $key, 'daily',
                'Today: '.$list->count().' '.($list->count() === 1 ? 'class' : 'classes').($extra ? " ({$extra} extra)" : ''),
                $list->map(fn ($e) => $e->start->format('H:i').' '.($e->type === 'extra' ? $e->label.' · ' : '').$e->course->subject->name.' ('.$e->course->section->label().', '.$e->room.')')->implode(' · '),
                route('attendance.index'),
            ));
            $sent++;
        }

        return $sent;
    }

    private function already(User $user, string $key): bool
    {
        return $user->notifications()->where('data->key', $key)->exists();
    }
}
