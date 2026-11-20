<?php

namespace Database\Seeders;

use App\Models\GuardianAlert;
use App\Models\LeaveRequest;
use App\Models\Section;
use App\Models\Student;
use App\Services\Attendance\AlertService;
use App\Services\Attendance\AttendanceStats;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * ~10 weeks of realistic registers up to today, generated from the timetable.
 * Every student has a stable attendance propensity so defaulters, streaks and
 * trends look like a real college. Bulk inserts keep this fast on SQLite.
 */
class AttendanceSeeder extends Seeder
{
    private const TOPICS = [
        'ENG' => ['Comprehension: “The Last Lesson”', 'Precis writing practice', 'Active and passive voice', 'Letter to the editor', 'Poem: “Daffodils” — explanation', 'Direct and indirect narration', 'Essay outline: Role of youth', 'Novel discussion: Chapter 4', 'Punctuation drill', 'Translation into English'],
        'URD' => ['Nazm: “Shikwa” tashreeh', 'Ghazal: Mir Taqi Mir', 'Afsana: “Kafan” tajziya', 'Mazmoon nigari', 'Khutoot nawesi', 'Qawaid: Ism aur Fe’l', 'Tashreeh-e-ashaar', 'Insha pardazi'],
        'ISL' => ['Surah Al-Hujurat: lessons', 'Seerat-un-Nabi ﷺ: Hijrah', 'Arkan-e-Islam: Zakat', 'Huqooq-ul-Ibad', 'Khutba Hajjatul Wida', 'Islamic ethics: Sabr', 'Hadith: Akhlaq'],
        'PST' => ['Two-Nation Theory', 'Lahore Resolution 1940', 'Constitution of 1973', 'Geography of Pakistan: rivers', 'Economic planning in Pakistan', 'Foreign policy objectives'],
        'PHY' => ['Vectors and equilibrium', 'Projectile motion', 'Work, power and energy', 'Circular motion', 'Fluid dynamics: Bernoulli', 'Oscillations: SHM', 'Waves: Doppler effect', 'Physical optics: Young’s experiment', 'Thermodynamics: first law', 'Practical: Vernier callipers'],
        'CHE' => ['Mole concept & stoichiometry', 'Atomic structure: quantum numbers', 'Chemical bonding: VSEPR', 'States of matter: gas laws', 'Chemical equilibrium', 'Reaction kinetics', 'Thermochemistry: Hess’s law', 'Electrochemistry', 'Practical: titration'],
        'BIO' => ['Biological molecules: proteins', 'Enzymes: mechanism of action', 'Cell structure & function', 'Bioenergetics: photosynthesis', 'Variety of life: viruses', 'Kingdom Fungi', 'Kingdom Plantae: bryophytes', 'Practical: slide of onion cells', 'Digestion in humans'],
        'MTH' => ['Complex numbers', 'Matrices and determinants', 'Quadratic equations', 'Partial fractions', 'Sequences and series', 'Permutations & combinations', 'Binomial theorem', 'Trigonometric identities', 'Limits & continuity', 'Differentiation rules', 'Integration by parts', 'Vector spaces', 'Eigenvalues'],
        'CS' => ['Basics of information technology', 'Number systems', 'Algorithms and flowcharts', 'C++: control structures', 'Arrays and strings', 'Functions & recursion', 'Linked lists', 'Stacks and queues', 'Normalization (1NF–3NF)', 'SQL joins', 'SDLC models', 'OSI model', 'Search strategies in AI', 'Lab: loops practice'],
        'ECO' => ['Demand and supply', 'Elasticity of demand', 'Theory of production', 'National income accounting', 'Inflation in Pakistan', 'Money and banking', 'Public finance'],
        'STA' => ['Measures of central tendency', 'Measures of dispersion', 'Probability', 'Random variables', 'Index numbers', 'Correlation & regression'],
        'COM' => ['Accounting equation', 'Journal entries', 'Ledger posting', 'Trial balance', 'Forms of business organisation', 'Partnership deed', 'Banking & cheques'],
        'POL' => ['State and its elements', 'Sovereignty', 'Democracy & dictatorship', 'Political parties', 'Constitution: features'],
    ];

    public function run(): void
    {
        mt_srand(2026);
        $tz = config('app.timezone');
        $now = Carbon::now($tz);
        $today = $now->copy()->startOfDay();
        $from = $today->copy()->subWeeks(10)->startOfWeek();
        $holidays = array_keys(config('attendance.holidays'));
        $lockHours = (int) config('attendance.lock_after_hours');

        $sections = Section::with(['academicSession', 'courses.subject', 'courses.slots'])->get();
        $students = Student::where('status', 'active')->orderBy('roll_no')->get(['id', 'section_id', 'name', 'roll_no', 'guardian_phone', 'phone'])->groupBy('section_id');

        // Stable propensity per student: most attend well, a tail are defaulters.
        $propensity = [];
        foreach ($students->flatten() as $s) {
            $r = mt_rand(1, 1000);
            $propensity[$s->id] = match (true) {
                $r <= 20 => mt_rand(380, 540) / 1000,   // chronic
                $r <= 105 => mt_rand(600, 740) / 1000,  // defaulters
                $r <= 190 => mt_rand(750, 830) / 1000,  // at risk
                default => mt_rand(850, 975) / 1000,
            };
        }

        // Leave requests (approved ones are honoured while generating records).
        $leaveDays = [];  // [student_id][Y-m-d] = true
        $leaveRows = [];
        $decider = DB::table('users')->where('role', 'principal')->value('id');
        $pool = $students->flatten()->shuffle()->take(34)->values();
        $reasons = ['Fever and flu — medical certificate attached', 'Sister’s wedding in Chakwal', 'Family emergency (grandfather hospitalised)', 'Dental surgery', 'Participation in inter-college debate (Lahore)', 'Travelling to village for family matter', 'Selected for district cricket trials', 'Viral infection, doctor advised rest'];
        foreach ($pool as $i => $s) {
            $status = $i < 9 ? 'pending' : ($i < 28 ? 'approved' : 'rejected');
            $start = $status === 'pending' ? $today->copy()->addDays(mt_rand(-1, 4)) : $today->copy()->subDays(mt_rand(3, 60));
            while ($start->isWeekend()) {
                $start->addDay();
            }
            $end = $start->copy()->addDays(mt_rand(0, 2));
            $leaveRows[] = [
                'student_id' => $s->id, 'from_date' => $start->toDateString(), 'to_date' => $end->toDateString(),
                'reason' => $reasons[mt_rand(0, count($reasons) - 1)], 'status' => $status,
                'decided_by' => $status === 'pending' ? null : $decider,
                'created_at' => $start->copy()->subDays(mt_rand(1, 3))->setTime(mt_rand(8, 14), mt_rand(0, 59)), 'updated_at' => $now,
            ];
            if ($status === 'approved') {
                foreach (CarbonPeriod::create($start, $end) as $d) {
                    $leaveDays[$s->id][$d->toDateString()] = true;
                }
            }
        }
        LeaveRequest::insert($leaveRows);

        $lectureId = (int) DB::table('lectures')->max('id');
        $lectures = [];
        $records = [];
        $flush = function (bool $force = false) use (&$lectures, &$records) {
            if ($force || count($records) >= 4000) {
                foreach (array_chunk($lectures, 400) as $c) {
                    DB::table('lectures')->insert($c);
                }
                foreach (array_chunk($records, 900) as $c) {
                    DB::table('attendance_records')->insert($c);
                }
                $lectures = [];
                $records = [];
            }
        };

        // Leave 1–3 of today's already-started lectures unmarked so "pending" is visible.
        $skipToday = [];
        $teacherCourse = DB::table('users')->where('email', 'teacher@fglacollege.edu.pk')->value('id');

        DB::transaction(function () use ($sections, $students, $propensity, $leaveDays, $from, $today, $now, $holidays, $lockHours, $teacherCourse, &$lectureId, &$lectures, &$records, $flush, &$skipToday) {
            foreach ($sections as $section) {
                $roster = $students->get($section->id, collect());
                if ($roster->isEmpty()) {
                    continue;
                }
                $start = $from->copy()->max(Carbon::parse($section->academicSession->starts_on)->startOfDay());
                $slotsByDay = [];
                foreach ($section->courses as $course) {
                    foreach ($course->slots as $slot) {
                        $slotsByDay[$slot->weekday][] = [$course, $slot];
                    }
                }
                foreach ($slotsByDay as &$list) {
                    usort($list, fn ($a, $b) => strcmp($a[1]->starts_at, $b[1]->starts_at));
                }
                unset($list);

                foreach (CarbonPeriod::create($start, $today) as $day) {
                    $date = $day->toDateString();
                    if ($day->isWeekend() || in_array($date, $holidays, true) || empty($slotsByDay[$day->dayOfWeekIso])) {
                        continue;
                    }
                    $isToday = $day->isSameDay($today);
                    $dayDip = match ($day->dayOfWeekIso) {
                        1 => 0.035, 5 => 0.05, default => 0.0
                    };
                    // whole-day absence decided once per student per day → realistic streaks
                    $dayAbsent = [];
                    foreach ($roster as $st) {
                        $p = $propensity[$st->id] - $dayDip;
                        $dayAbsent[$st->id] = (mt_rand(0, 10000) / 10000) > $p;
                    }
                    foreach ($slotsByDay[$day->dayOfWeekIso] as $idx => [$course, $slot]) {
                        $startsAt = Carbon::parse($date.' '.$slot->starts_at, config('app.timezone'));
                        if ($isToday) {
                            if ($startsAt->greaterThan($now)) {
                                continue;
                            }
                            // leave some of today's registers pending (incl. one of the demo teacher's)
                            if (($course->teacher_id === $teacherCourse && ! in_array('t', $skipToday, true)) || mt_rand(1, 100) <= 4) {
                                $skipToday[] = $course->teacher_id === $teacherCourse ? 't' : $course->id;

                                continue;
                            }
                        }
                        $lectureId++;
                        $c = ['P' => 0, 'A' => 0, 'L' => 0, 'E' => 0];
                        foreach ($roster as $st) {
                            if (isset($leaveDays[$st->id][$date])) {
                                $status = 'E';
                            } elseif ($dayAbsent[$st->id]) {
                                $status = mt_rand(1, 100) <= 92 ? 'A' : 'P'; // occasionally turns up for one class
                            } else {
                                $r = mt_rand(1, 1000);
                                $status = $r <= 18 ? 'A' : ($r <= ($idx === 0 ? 75 : 35) ? 'L' : 'P');
                            }
                            $c[$status]++;
                            $records[] = ['lecture_id' => $lectureId, 'student_id' => $st->id, 'status' => $status, 'remarks' => null, 'created_at' => $startsAt, 'updated_at' => $startsAt];
                        }
                        $topics = self::TOPICS[preg_replace('/[-\d].*$/', '', $course->subject->code)] ?? ['Lecture'];
                        $lectures[] = [
                            'id' => $lectureId, 'course_id' => $course->id, 'timetable_slot_id' => $slot->id,
                            'held_on' => $date, 'starts_at' => $slot->starts_at,
                            'topic' => $topics[mt_rand(0, count($topics) - 1)],
                            'marked_by' => $course->teacher_id,
                            'present_count' => $c['P'], 'absent_count' => $c['A'], 'late_count' => $c['L'], 'leave_count' => $c['E'],
                            'locked_at' => $startsAt->copy()->addHours($lockHours),
                            'created_at' => $startsAt->copy()->addMinutes(mt_rand(3, 25)), 'updated_at' => $startsAt,
                        ];
                        $flush();
                    }
                }
            }
            $flush(true);
        });

        $this->seedAlerts($today, $now);
    }

    private function seedAlerts(Carbon $today, Carbon $now): void
    {
        $alerts = app(AlertService::class);
        $stats = app(AttendanceStats::class);
        $threshold = config('attendance.threshold');
        $rates = $stats->studentRates();
        $students = Student::whereIn('id', array_keys($rates))->get()->keyBy('id');
        $rows = [];

        foreach ($rates as $id => $r) {
            $st = $students[$id] ?? null;
            if (! $st || $r['rate'] === null || $r['rate'] >= $threshold) {
                continue;
            }
            $sent = $today->copy()->subDays(mt_rand(2, 20))->setTime(mt_rand(9, 15), mt_rand(0, 59));
            $rows[] = ['student_id' => $id, 'channel' => 'sms', 'kind' => 'short_attendance', 'to' => $st->guardian_phone ?: $st->phone,
                'message' => $alerts->template('short_attendance', $st, ['pct' => number_format($r['rate'] + mt_rand(1, 4), 1)]),
                'status' => mt_rand(1, 100) <= 94 ? 'sent' : 'failed', 'sent_at' => $sent, 'created_at' => $sent, 'updated_at' => $sent];
        }

        // Today's absentees → queued alerts, waiting for the principal's office to send.
        $absentToday = DB::table('attendance_records as r')->join('lectures as l', 'l.id', '=', 'r.lecture_id')
            ->where('l.held_on', $today->toDateString())->where('r.status', 'A')
            ->distinct()->limit(26)->pluck('r.student_id');
        foreach (Student::whereIn('id', $absentToday)->get() as $st) {
            $rows[] = ['student_id' => $st->id, 'channel' => 'sms', 'kind' => 'absent_today', 'to' => $st->guardian_phone ?: $st->phone,
                'message' => $alerts->template('absent_today', $st, ['date' => $today->format('j M'), 'subject' => 'today’s classes']),
                'status' => 'queued', 'sent_at' => null, 'created_at' => $now, 'updated_at' => $now];
        }

        // A few historical absent alerts and consecutive-absence alerts that were already sent.
        foreach (Student::inRandomOrder()->limit(30)->get() as $i => $st) {
            $at = $today->copy()->subDays(mt_rand(1, 45))->setTime(mt_rand(10, 14), mt_rand(0, 59));
            $kind = $i % 4 === 0 ? 'consecutive_absence' : 'absent_today';
            $rows[] = ['student_id' => $st->id, 'channel' => 'sms', 'kind' => $kind, 'to' => $st->guardian_phone ?: $st->phone,
                'message' => $alerts->template($kind, $st, ['date' => $at->format('j M'), 'subject' => 'today’s classes', 'days' => 3]),
                'status' => 'sent', 'sent_at' => $at, 'created_at' => $at, 'updated_at' => $at];
        }

        foreach (array_chunk($rows, 200) as $c) {
            GuardianAlert::insert($c);
        }
    }
}
