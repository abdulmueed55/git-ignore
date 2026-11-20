<?php

namespace Database\Seeders;

use App\Models\ActivityLog;
use App\Models\AdmissionApplication;
use App\Models\Lecture;
use App\Models\Post;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * Builds an audit trail from the data the other seeders created, so the
 * activity log and dashboard feed reflect what "happened" in the last fortnight.
 */
class ActivitySeeder extends Seeder
{
    public function run(): void
    {
        mt_srand(2026);
        $rows = [];
        $add = function (?int $userId, string $action, string $text, $subject, Carbon $at) use (&$rows) {
            $rows[] = [
                'user_id' => $userId, 'action' => $action, 'description' => $text,
                'subject_type' => $subject?->getMorphClass(), 'subject_id' => $subject?->getKey(),
                'properties' => null, 'ip' => '10.0.'.mt_rand(1, 9).'.'.mt_rand(2, 250),
                'created_at' => $at, 'updated_at' => $at,
            ];
        };

        // Sign-ins
        foreach (User::where('is_active', true)->get() as $u) {
            foreach (range(0, 9) as $d) {
                if (mt_rand(1, 10) <= 6) {
                    $add($u->id, 'auth.login', 'Signed in', null, now()->subDays($d)->setTime(mt_rand(7, 9), mt_rand(0, 59)));
                }
            }
        }

        // Registers marked (a sample of the last week)
        Lecture::with('course.subject:id,name', 'course.section.program:id,code')
            ->where('held_on', '>=', now()->subDays(7)->toDateString())
            ->whereNotNull('marked_by')->inRandomOrder()->limit(60)->get()
            ->each(function (Lecture $l) use ($add) {
                $at = Carbon::parse($l->getRawOriginal('held_on').' '.($l->starts_at ?? '09:00'))->addMinutes(mt_rand(5, 40));
                if ($at->isFuture()) {
                    return;
                }
                $total = $l->present_count + $l->absent_count + $l->late_count + $l->leave_count;
                $add($l->marked_by, 'attendance.saved', sprintf('Marked attendance for %s — %s %s (%d/%d present)',
                    $l->course->subject->name, $l->course->section->program->code, $l->course->section->name,
                    $l->present_count + $l->late_count, $total), $l, $at);
            });

        // Admissions decisions
        if (class_exists(AdmissionApplication::class)) {
            AdmissionApplication::whereNotNull('reviewed_by')->whereNotNull('reviewed_at')
                ->where('reviewed_at', '>=', now()->subDays(14))->limit(40)->get()
                ->each(fn ($a) => $add($a->reviewed_by, 'admissions.'.$a->status,
                    sprintf('Marked application %s (%s) as %s', $a->tracking_no, $a->name ?? 'applicant', strtolower($a->statusLabel())), $a, $a->reviewed_at));
        }

        // Website publishing
        Post::whereNotNull('author_id')->where('published_at', '<=', now())->where('published_at', '>=', now()->subDays(14))->get()
            ->each(fn ($p) => $add($p->author_id, 'studio.published', 'Published '.$p->type.' “'.$p->title.'”', $p, $p->published_at));

        usort($rows, fn ($a, $b) => $a['created_at'] <=> $b['created_at']);
        foreach (array_chunk($rows, 200) as $chunk) {
            ActivityLog::insert($chunk);
        }
    }
}
