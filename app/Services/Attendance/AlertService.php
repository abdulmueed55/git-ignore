<?php

namespace App\Services\Attendance;

use App\Models\GuardianAlert;
use App\Models\Lecture;
use App\Models\Student;
use Illuminate\Support\Collection;

/**
 * Queues guardian SMS alerts after a register is saved and delivers queued
 * alerts through the configured SmsGateway. Messages are bilingual:
 * English followed by Roman Urdu, so every parent can read them.
 */
class AlertService
{
    public const KINDS = [
        'absent_today' => 'Absent today',
        'short_attendance' => 'Below threshold',
        'consecutive_absence' => 'Consecutive absence',
    ];

    public function __construct(private AttendanceStats $stats) {}

    public function template(string $kind, Student $s, array $v = []): string
    {
        $who = "{$s->name} ({$s->roll_no})";
        $phone = config('attendance.sms.college_phone');
        $threshold = config('attendance.threshold');

        return match ($kind) {
            'absent_today' => "FGLA College: {$who} was absent from {$v['subject']} on {$v['date']}. "
                ."Muhtaram walidain, aap ke bachay aaj ({$v['date']}) class mein ghair-haazir thay. Rabta: {$phone}",
            'short_attendance' => "FGLA College: attendance of {$who} is {$v['pct']}%, below the required {$threshold}%. "
                ."Aap ke bachay ki haazri {$v['pct']}% hai jo {$threshold}% se kam hai — imtihan mein shirkat ke liye {$threshold}% laazmi hai. Rabta: {$phone}",
            'consecutive_absence' => "FGLA College: {$who} has been absent for {$v['days']} consecutive days. "
                ."Aap ke bachay musalsal {$v['days']} din se college nahi aa rahe. Barah-e-karam fori rabta karein: {$phone}",
            default => "FGLA College notice regarding {$who}.",
        };
    }

    /**
     * Called after a register is saved. $newlyAbsent = students marked A who were not A before.
     * Returns the number of alerts queued.
     */
    public function afterSave(Lecture $lecture, array $newlyAbsent): int
    {
        if (! $newlyAbsent) {
            return 0;
        }
        $lecture->loadMissing('course.subject');
        $students = Student::whereIn('id', $newlyAbsent)->get();
        $threshold = config('attendance.threshold');
        $needed = (int) config('attendance.consecutive_absence_alert');
        $rates = $this->stats->studentRates($newlyAbsent);
        $days = $this->stats->dayStatuses($newlyAbsent, $lecture->held_on->copy()->subDays(21)->toDateString(), $lecture->held_on->toDateString());
        $queued = 0;

        foreach ($students as $s) {
            $to = $s->guardian_phone ?: $s->phone;
            if (! $to) {
                continue;
            }

            // Same-day absence: one alert per student per day, only for today's registers.
            if ($lecture->held_on->isToday() && ! $this->recent($s->id, 'absent_today', 0)) {
                $this->queue($s, 'absent_today', $to, ['subject' => $lecture->course->subject->name, 'date' => $lecture->held_on->format('j M')]);
                $queued++;
            }

            $rate = $rates[$s->id]['rate'] ?? null;
            if ($rate !== null && $rate < $threshold && ! $this->recent($s->id, 'short_attendance', 7)) {
                $this->queue($s, 'short_attendance', $to, ['pct' => number_format($rate, 1)]);
                $queued++;
            }

            $streak = AttendanceStats::streaks($days[$s->id] ?? [])['current'];
            if ($streak >= $needed && ! $this->recent($s->id, 'consecutive_absence', 3)) {
                $this->queue($s, 'consecutive_absence', $to, ['days' => $streak]);
                $queued++;
            }
        }

        return $queued;
    }

    /** Queue a short-attendance notice for each student (bulk action from the defaulters list). */
    public function notifyDefaulters(Collection $students, array $rates): int
    {
        $n = 0;
        foreach ($students as $s) {
            $to = $s->guardian_phone ?: $s->phone;
            if (! $to || $this->recent($s->id, 'short_attendance', 1)) {
                continue;
            }
            $this->queue($s, 'short_attendance', $to, ['pct' => number_format($rates[$s->id]['rate'] ?? 0, 1)]);
            $n++;
        }

        return $n;
    }

    /** Deliver queued alerts. Returns [sent, failed]. */
    public function sendQueued(Collection $alerts): array
    {
        $gateway = LogSmsGateway::resolve();
        $sent = $failed = 0;
        foreach ($alerts as $alert) {
            $result = $gateway->send($alert->to, $alert->message);
            $alert->update($result['ok']
                ? ['status' => 'sent', 'sent_at' => now()]
                : ['status' => 'failed']);
            $result['ok'] ? $sent++ : $failed++;
        }

        return [$sent, $failed];
    }

    private function queue(Student $s, string $kind, string $to, array $vars): GuardianAlert
    {
        return GuardianAlert::create([
            'student_id' => $s->id, 'channel' => 'sms', 'kind' => $kind, 'to' => $to,
            'message' => $this->template($kind, $s, $vars), 'status' => 'queued',
        ]);
    }

    private function recent(int $studentId, string $kind, int $days): bool
    {
        return GuardianAlert::where('student_id', $studentId)->where('kind', $kind)
            ->where('created_at', '>=', now()->subDays($days)->startOfDay())->exists();
    }
}
