<?php

namespace Database\Seeders;

use App\Models\AcademicSession;
use App\Models\ActivityLog;
use App\Models\AdmissionApplication;
use App\Models\AdmissionCycle;
use App\Models\ApplicationDocument;
use App\Models\MeritList;
use App\Models\Program;
use App\Models\Student;
use App\Models\User;
use App\Services\Admissions\AutoFiller;
use App\Services\Admissions\CrossDocumentVerifier;
use App\Services\Admissions\MeritCalculator;
use App\Services\Admissions\MeritListService;
use App\Services\Admissions\SpecimenDocuments;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Two admission cycles with genuine, verifier-produced results:
 *  - "Fall 2026 Intake" (closed): built from the year-1 students already enrolled, plus rejected/waitlisted applicants.
 *  - "Admissions 2026–27 — 1st Year & BS" (open): every programme, applications at every stage incl. real document mismatches.
 * Admission is offered only into 1st year (HSSC Part-I) and the first semester of BS.
 */
class AdmissionSeeder extends Seeder
{
    private SpecimenDocuments $specimens;

    private AutoFiller $filler;

    private CrossDocumentVerifier $verifier;

    private MeritCalculator $merit;

    private array $photos = [];

    private const SCHOOLS = ['FG Boys High School Chaklala', 'Army Public School Westridge', 'FG Model School Lalkurti', 'Govt. High School No. 1 Rawalpindi', 'Fazaia Inter College', 'Punjab Group of Colleges', 'Islamabad Model College for Boys F-7/3', 'The Educators Satellite Town'];

    public function run(): void
    {
        mt_srand(2027);
        Storage::disk('local')->deleteDirectory('admissions');
        $this->specimens = new SpecimenDocuments;
        $this->filler = new AutoFiller;
        $this->verifier = new CrossDocumentVerifier;
        $this->merit = new MeritCalculator;
        foreach (['male', 'female'] as $g) {
            [$path] = $this->specimens->make('photo', ['gender' => $g], 'admissions/specimen-'.$g);
            $this->photos[$g] = $path;
        }

        $officer = User::where('role', 'admission_officer')->first();
        auth()->setUser($officer); // activity log attribution
        $programs = Program::all()->keyBy('code')->toBase(); // base collection: only()/except() by code

        $this->closedCycle($programs, $officer);
        $this->openCycle($programs, $officer);
        auth()->forgetUser();
    }

    // ------------------------------------------------------------------ closed cycle

    private function closedCycle($programs, User $officer): void
    {
        $session = AcademicSession::where('name', '2026–2028')->first();
        $cycle = AdmissionCycle::create([
            'academic_session_id' => $session->id,
            'title' => 'Fall 2026 Intake — Intermediate & BS',
            'opens_at' => Carbon::parse('2026-06-22 09:00'),
            'closes_at' => Carbon::parse('2026-08-08 17:00'),
            'application_fee' => 1500,
            'program_ids' => $programs->except('ADP-SC')->pluck('id')->values()->all(),
            'status' => 'closed',
            'instructions' => "Online applications for 1st Year F.Sc. (Pre-Medical, Pre-Engineering), General Science, I.C.S., I.Com. and F.A., and the 1st semester of BS English, BS Information Technology and BS Computer Science.\nUpload clear photos of your CNIC / B-Form, father's CNIC, result cards and domicile. Merit lists are displayed on the notice board and website.",
        ]);

        $window = [Carbon::parse('2026-06-22'), Carbon::parse('2026-08-08')];

        // Admitted = students already enrolled in first-year sections (their application history).
        $students = Student::with('program', 'section', 'academicSession')
            ->whereHas('section', fn ($q) => $q->where('year', 1))
            ->get()->groupBy('program_id');

        foreach ($students as $programId => $group) {
            $program = $programs->firstWhere('id', $programId);
            foreach ($group->shuffle()->take(13) as $st) {
                $p = $this->person($program, [
                    'name' => $st->name, 'father_name' => $st->father_name, 'cnic' => $st->cnic, 'father_cnic' => $st->father_cnic,
                    'date_of_birth' => $st->date_of_birth->format('Y-m-d'), 'gender' => $st->gender, 'phone' => $st->phone,
                    'guardian_phone' => $st->guardian_phone, 'email' => $st->email, 'address' => $st->address, 'city' => $st->city, 'domicile' => $st->domicile,
                ], strong: true);
                $app = $this->build($cycle, $program, $p, $this->between(...$window), 'submitted');
                $app->update(['status' => 'admitted', 'reviewed_by' => $officer->id, 'reviewed_at' => $app->submitted_at->copy()->addDays(mt_rand(6, 14))]);
                $st->update(['admission_application_id' => $app->id]);
            }
        }

        // Unsuccessful applicants: below merit / failed verification.
        foreach ($programs->except('ADP-SC') as $program) {
            for ($i = 0; $i < 4; $i++) {
                $variant = $i === 0 ? 'marks' : null;
                $p = $this->person($program, [], strong: false);
                $app = $this->build($cycle, $program, $p, $this->between(...$window), 'submitted', $variant);
                $status = $i === 0 ? 'rejected' : ($i === 1 ? 'rejected' : 'waitlisted');
                $remarks = $i === 0 ? 'Marks on the result card do not match the form. Application cancelled.' : ($i === 1 ? 'Merit below closing merit of all lists.' : 'Waitlisted — seats filled in list 2.');
                $app->update(['status' => $status, 'remarks' => $remarks, 'reviewed_by' => $officer->id, 'reviewed_at' => $app->submitted_at->copy()->addDays(10)]);
            }
        }

        // Published merit lists reflect who was admitted.
        foreach ($programs->except('ADP-SC') as $program) {
            $admitted = AdmissionApplication::where('admission_cycle_id', $cycle->id)->where('program_id', $program->id)->where('status', 'admitted')->orderByDesc('merit_score')->get();
            if ($admitted->isEmpty()) {
                continue;
            }
            $chunks = $admitted->count() > 8 ? [$admitted->take(9), $admitted->slice(9)] : [$admitted];
            foreach (array_values(array_filter($chunks, fn ($c) => $c->isNotEmpty())) as $n => $chunk) {
                $list = MeritList::create([
                    'admission_cycle_id' => $cycle->id, 'program_id' => $program->id, 'list_no' => $n + 1,
                    'seats_offered' => $chunk->count(), 'closing_merit' => $chunk->last()->merit_score, 'status' => 'published',
                    'published_at' => Carbon::parse('2026-08-14')->addDays($n * 6), 'fee_deadline' => Carbon::parse('2026-08-21')->addDays($n * 6),
                ]);
                foreach ($chunk->values() as $i => $a) {
                    $list->entries()->create(['admission_application_id' => $a->id, 'position' => $i + 1, 'merit_score' => $a->merit_score]);
                }
            }
        }
    }

    // ------------------------------------------------------------------ open cycle

    private function openCycle($programs, User $officer): void
    {
        $session = AcademicSession::where('name', '2026–2030')->first();
        $offered = $programs->filter(fn ($p) => $p->is_active);
        $cycle = AdmissionCycle::create([
            'academic_session_id' => $session->id,
            'title' => 'Admissions 2026–27 — Intermediate 1st Year & BS',
            'opens_at' => now()->subDays(18)->setTime(9, 0),
            'closes_at' => now()->addDays(21)->setTime(17, 0),
            'application_fee' => 1500,
            'program_ids' => $offered->pluck('id')->values()->all(),
            'status' => 'open',
            'instructions' => "Admission is offered in 1st Year (HSSC Part-I) for F.Sc. Pre-Medical, F.Sc. Pre-Engineering, General Science, I.C.S., I.Com. and F.A., and in the 1st semester of BS English, BS Information Technology and BS Computer Science.\nEligibility: more than 60% in SSC for the science groups; intermediate with the minimum marks shown for each BS programme. Hafiz-e-Quran applicants receive 20 additional marks.\nKeep your tracking number and PIN to check status and merit lists online.",
        ]);
        $from = $cycle->opens_at->copy();

        // [program, count, scenario list]
        $plan = [
            ['FSC-PM', 12], ['FSC-PE', 9], ['FSC-GS', 6], ['ICS', 9], ['ICOM', 6], ['FA', 6],
            ['BSCS', 14], ['BSENG', 10], ['BSIT', 9],
        ];
        $variants = ['name_partial', 'dob', 'marks', 'father', 'wrong_doc', 'inter_roll', 'blurry', 'duplicate'];
        $k = 0;
        $dupeSource = null;
        foreach ($plan as [$code, $count]) {
            $program = $offered[$code];
            for ($i = 0; $i < $count; $i++, $k++) {
                $when = $this->between($from, now()->subHour());
                if ($i < 2) {
                    // drafts: started, still uploading
                    $p = $this->person($program, [], strong: mt_rand(0, 1) === 1);
                    $this->build($cycle, $program, $p, $when, 'draft', null, partial: true);

                    continue;
                }
                $variant = ($i % 3 === 2 && $variants) ? array_shift($variants) : null;
                $p = $this->person($program, [], strong: $i % 5 !== 4);
                if ($variant === 'duplicate') {
                    if (! $dupeSource) {
                        $dupeSource = $p;
                        $variant = null;
                    } else {
                        $p['cnic'] = $dupeSource['cnic'];
                    }
                }
                $this->build($cycle, $program, $p, $when, 'submitted', $variant);
            }
        }

        // Make sure the duplicate case exists even if the rotation missed it.
        if ($dupeSource) {
            $p = $this->person($offered['BSENG'], ['cnic' => $dupeSource['cnic']], strong: true);
            $this->build($cycle, $offered['BSENG'], $p, now()->subDays(2), 'submitted');
            AdmissionApplication::where('admission_cycle_id', $cycle->id)->where('cnic', $dupeSource['cnic'])->get()
                ->each(fn ($a) => $this->verifier->verify($a->load('documents', 'program')));
        }

        // The AI only recommends — the admission officer has verified most AI-clear applications;
        // a few are left in the queue so "ready to verify" is not empty.
        AdmissionApplication::where('admission_cycle_id', $cycle->id)->where('status', 'under_review')->where('verification_status', 'clear')
            ->orderBy('id')->get()
            ->each(function ($a, $i) use ($officer) {
                if ($i % 5 !== 4) {
                    $a->update(['status' => 'verified', 'reviewed_by' => $officer->id, 'reviewed_at' => ($a->submitted_at ?? now())->copy()->addDay()->min(now())]);
                }
            });

        // A couple of applicants below the minimum: rejected with reason.
        foreach (AdmissionApplication::where('admission_cycle_id', $cycle->id)->where('status', 'verified')->get() as $a) {
            if ($this->merit->eligibilityPercentage($a->load('program')) < $a->program->min_percentage) {
                $a->update(['status' => 'rejected', 'remarks' => "Intermediate marks below the {$a->program->min_percentage}% minimum for {$a->program->code}.", 'reviewed_by' => $officer->id, 'reviewed_at' => now()->subDays(1)]);
            }
        }

        // BSCS list #1 published, BSENG list #1 prepared as a draft.
        $service = new MeritListService;
        $list = $service->generate($cycle, $offered['BSCS'], 10);
        $list->entries()->count() && $service->publish($list, now()->addDays(6)->toDateString());
        $list?->update(['published_at' => now()->subDays(2)]);
        $service->generate($cycle, $offered['BSENG'], 8);
        $pm = $service->generate($cycle, $offered['FSC-PM'], 6);
        $pm->entries()->count() && $service->publish($pm, now()->addDays(5)->toDateString());
        $pm->update(['published_at' => now()->subDay()]);
    }

    /** Demo applicants get a random, unknown PIN; hashing once keeps seeding fast. */
    private function pinHash(): string
    {
        static $hash;

        return $hash ??= Hash::make((string) random_int(100000, 999999));
    }

    // ------------------------------------------------------------------ helpers

    private function between(Carbon $a, Carbon $b): Carbon
    {
        return Carbon::createFromTimestamp(mt_rand($a->timestamp, max($a->timestamp, $b->timestamp)))->setTimezone(config('app.timezone'));
    }

    /** A plausible applicant (identity + academic record). */
    private function person(Program $program, array $over, bool $strong): array
    {
        $female = ($over['gender'] ?? null) === 'female' || (! isset($over['gender']) && in_array($program->code, ['BSENG', 'BSCS', 'BSIT'], true) && mt_rand(1, 100) <= 40);
        $name = $over['name'] ?? ($female ? AcademicSeeder::FEMALE[mt_rand(0, count(AcademicSeeder::FEMALE) - 1)] : AcademicSeeder::MALE[mt_rand(0, count(AcademicSeeder::MALE) - 1)]);
        $inter = $program->basedOnInter();
        $age = $inter ? mt_rand(18, 20) : mt_rand(15, 17);
        $matricYear = (int) now()->year - ($inter ? mt_rand(2, 3) : 1);
        $interYear = $matricYear + 2;
        $mt = $matricYear >= 2024 ? 1200 : 1100;
        $it = $interYear >= 2025 ? 1200 : 1100;
        $band = $strong ? [0.72, 0.95] : [0.48, 0.74];
        $boards = ['BISE Rawalpindi', 'BISE Rawalpindi', 'BISE Rawalpindi', 'FBISE Islamabad', 'FBISE Islamabad', 'BISE Gujranwala', 'BISE Lahore'];

        $p = [
            'name' => $name,
            'father_name' => AcademicSeeder::FATHERS[mt_rand(0, count(AcademicSeeder::FATHERS) - 1)],
            'cnic' => sprintf('37405-%07d-%d', mt_rand(1000000, 9999999), $female ? 2 * mt_rand(1, 4) : 2 * mt_rand(0, 4) + 1),
            'father_cnic' => sprintf('37405-%07d-%d', mt_rand(1000000, 9999999), 2 * mt_rand(0, 4) + 1),
            'date_of_birth' => now()->subYears($age)->subDays(mt_rand(0, 360))->format('Y-m-d'),
            'gender' => $female ? 'female' : 'male',
            'religion' => mt_rand(1, 40) === 1 ? 'Christianity' : 'Islam',
            'phone' => sprintf('03%02d-%07d', mt_rand(0, 49), mt_rand(1000000, 9999999)),
            'guardian_phone' => sprintf('03%02d-%07d', mt_rand(0, 49), mt_rand(1000000, 9999999)),
            'email' => Str::slug($name, '.').mt_rand(10, 99).'@gmail.com',
            'address' => 'House '.mt_rand(1, 900).', Street '.mt_rand(1, 40).', '.AcademicSeeder::AREAS[mt_rand(0, count(AcademicSeeder::AREAS) - 1)],
            'city' => AcademicSeeder::CITIES[mt_rand(0, count(AcademicSeeder::CITIES) - 1)],
            'domicile' => mt_rand(1, 10) > 2 ? 'Rawalpindi' : ['Attock', 'Chakwal', 'Jhelum', 'Islamabad'][mt_rand(0, 3)],
            'quota' => (function () {
                $r = mt_rand(1, 100);

                return match (true) {
                    $r <= 53 => 'open', $r <= 78 => 'serving_forces', $r <= 88 => 'retired_forces',
                    $r <= 97 => 'defence_employee', $r <= 98 => 'disabled', default => 'fg_employee',
                };
            })(),
            'is_hafiz' => mt_rand(1, 18) === 1,
            'matric_board' => $boards[mt_rand(0, count($boards) - 1)],
            'matric_roll_no' => (string) mt_rand(100000, 699999),
            'matric_year' => $matricYear,
            'matric_group' => in_array($program->code, ['FA', 'ICOM'], true) && mt_rand(0, 1) ? 'Humanities' : 'Science',
            'matric_total' => $mt,
            'matric_obtained' => (int) round($mt * mt_rand((int) ($band[0] * 1000), (int) ($band[1] * 1000)) / 1000),
        ];
        if ($inter) {
            $p += [
                'inter_board' => $p['matric_board'],
                'inter_roll_no' => (string) mt_rand(200000, 799999),
                'inter_year' => $interYear,
                'inter_group' => ['Pre-Engineering', 'ICS', 'Pre-Medical', 'Humanities'][mt_rand(0, $program->code === 'BSENG' ? 3 : 2)],
                'inter_total' => $it,
                'inter_obtained' => (int) round($it * mt_rand((int) ($band[0] * 1000), (int) ($band[1] * 1000)) / 1000),
            ];
        }

        return array_merge($p, $over);
    }

    /**
     * Create the application, draw + attach documents with the "true" values, optionally
     * tamper one side (variant), auto-fill and run the real verifier + merit calculator.
     */
    private function build(AdmissionCycle $cycle, Program $program, array $p, Carbon $when, string $status, ?string $variant = null, bool $partial = false): AdmissionApplication
    {
        $second = $cycle->program_ids ? Program::whereIn('id', $cycle->program_ids)->where('id', '!=', $program->id)->inRandomOrder()->value('id') : null;

        // What the applicant typed on step 1 / step 3 (identity + contact). Academic + family
        // details are left for the AI to auto-fill from documents, like the real flow.
        $typed = array_intersect_key($p, array_flip(['name', 'cnic', 'phone', 'email', 'quota', 'is_hafiz']));
        $app = AdmissionApplication::create($typed + [
            'admission_cycle_id' => $cycle->id,
            'program_id' => $program->id,
            'second_program_id' => mt_rand(0, 2) ? $second : null,
            'tracking_no' => AdmissionApplication::newTrackingNo(),
            'access_pin' => $this->pinHash(),
            'status' => 'draft',
        ]);
        $app->forceFill(['created_at' => $when, 'updated_at' => $when])->saveQuietly();

        // Document truth (may be tampered by variant)
        $doc = $p;
        $form = [];
        switch ($variant) {
            case 'name_partial':  // e.g. "Usman Tariq" typed, card says "Usman Tarik"
                $doc['name'] = preg_replace('/q\b/', 'k', $p['name']) === $p['name'] ? $p['name'].' Khan' : preg_replace('/q\b/', 'k', $p['name']);
                break;
            case 'dob':
                $form['date_of_birth'] = Carbon::parse($p['date_of_birth'])->addYear()->format('Y-m-d');
                break;
            case 'marks':
                $form['matric_obtained'] = min($p['matric_total'] - 3, $p['matric_obtained'] + 100);
                break;
            case 'father':
                $doc['father_name'] = AcademicSeeder::FATHERS[(array_search($p['father_name'], AcademicSeeder::FATHERS) + 7) % count(AcademicSeeder::FATHERS)];
                $form['father_name'] = $p['father_name'];
                break;
            case 'inter_roll':
                if (isset($p['inter_roll_no'])) {
                    $form['inter_roll_no'] = (string) ((int) $p['inter_roll_no'] + 1043);
                }
                break;
        }

        $types = $app->requiredDocumentTypes();
        if (mt_rand(0, 2) === 0) {
            $types[] = 'character_certificate';
        }
        if ($partial) {
            $types = array_slice($types, 0, mt_rand(0, 3));
        }

        foreach ($types as $type) {
            $this->attach($app, $type, $doc, $variant);
        }

        // Applicant-typed corrections that differ from documents (the tampered side)
        if ($form) {
            $app->fill($form)->save();
        }
        $app->load('documents');
        if (! $partial) {
            $this->filler->fill($app);
        }

        if ($status === 'draft') {
            $app->refresh();
            $this->verifier->verify($app->load('documents', 'program'));
            $this->merit->apply($app->fresh('program'));

            return $app;
        }

        // Step-3 fields the applicant completes by hand
        $app->fill(array_intersect_key($p, array_flip(['religion', 'guardian_phone', 'address', 'city', 'gender'])) + ['gender' => $p['gender']]);
        foreach (['father_name', 'father_cnic', 'date_of_birth', 'domicile', 'matric_group', 'inter_group'] as $f) {
            if (blank($app->{$f}) && isset($p[$f])) {
                $app->{$f} = $p[$f];
            }
        }
        $app->status = 'submitted';
        $app->submitted_at = $when->copy()->addMinutes(mt_rand(8, 90));
        $app->save();

        $this->verifier->verify($app->fresh(['documents', 'program']));
        $app = $app->fresh('program');
        $this->merit->apply($app);

        ActivityLog::create([
            'user_id' => null, 'action' => 'admissions.submitted',
            'description' => "Application {$app->tracking_no} submitted online by {$app->name}",
            'subject_type' => $app->getMorphClass(), 'subject_id' => $app->id,
            'created_at' => $app->submitted_at, 'updated_at' => $app->submitted_at,
        ]);

        return $app;
    }

    private function attach(AdmissionApplication $app, string $type, array $p, ?string $variant): void
    {
        $dir = "admissions/{$app->id}";
        if ($type === 'photo') {
            ApplicationDocument::create([
                'admission_application_id' => $app->id, 'type' => 'photo', 'path' => $this->photos[$p['gender']], 'original_name' => 'passport-photo.jpg',
                'mime' => 'image/jpeg', 'size' => 18400, 'extraction_status' => 'skipped', 'extracted_by' => 'seed', 'extracted_at' => $app->created_at,
            ]);

            return;
        }

        $data = match ($type) {
            'cnic' => ['name' => $p['name'], 'father_name' => $p['father_name'], 'cnic_number' => $p['cnic'], 'date_of_birth' => $p['date_of_birth'], 'gender' => $p['gender']],
            'father_cnic' => ['name' => $p['father_name'], 'father_name' => AcademicSeeder::FATHERS[crc32($p['father_name']) % count(AcademicSeeder::FATHERS)], 'cnic_number' => $p['father_cnic'], 'date_of_birth' => Carbon::parse($p['date_of_birth'])->subYears(mt_rand(26, 34))->format('Y-m-d'), 'gender' => 'male'],
            'matric_dmc' => $this->dmc($p, 'matric'),
            'inter_dmc' => $this->dmc($p, 'inter'),
            'domicile' => ['name' => $p['name'], 'father_name' => $p['father_name'], 'district' => $p['domicile']],
            'character_certificate' => ['name' => $p['name'], 'father_name' => $p['father_name'], 'institution' => self::SCHOOLS[mt_rand(0, count(self::SCHOOLS) - 1)]],
        };

        $issues = [];
        $typeOk = true;
        $status = 'done';
        $error = null;
        $conf = mt_rand(86, 98);
        if ($variant === 'wrong_doc' && $type === 'domicile') {
            $typeOk = false;
            $issues = ['This appears to be a school leaving certificate, not a domicile.'];
            $conf = 64;
            $data = ['name' => $p['name'], 'father_name' => $p['father_name'], 'district' => null];
        }
        if ($variant === 'blurry' && $type === 'father_cnic') {
            $status = 'failed';
            $error = 'Image too blurry to read the identity number — please re-upload a sharper photo.';
        }

        [$path, $mime, $size] = $this->specimens->make($type, $data + ['subjects' => $data['subjects'] ?? []], $dir);
        unset($data['subjects']);

        ApplicationDocument::create([
            'admission_application_id' => $app->id,
            'type' => $type,
            'path' => $path,
            'original_name' => Str::slug(AdmissionApplication::DOCUMENT_TYPES[$type]).'.jpg',
            'mime' => $mime,
            'size' => $size,
            'extraction_status' => $status,
            'extracted' => $status === 'done' ? $data + ['document_type_matches' => $typeOk, 'confidence' => $conf, 'issues' => $issues] : null,
            'confidence' => $status === 'done' ? $conf : null,
            'extraction_error' => $error,
            'extracted_by' => 'seed',
            'extracted_at' => $app->created_at->copy()->addMinutes(2),
        ]);
    }

    private function dmc(array $p, string $lvl): array
    {
        $total = $p[$lvl.'_total'] ?? 1100;
        $obtained = $p[$lvl.'_obtained'] ?? 0;
        $subjects = $lvl === 'matric'
            ? [['English', 150, 0], ['Urdu', 150, 0], ['Islamiyat', 100, 0], ['Pakistan Studies', 100, 0], ['Mathematics', 150, 0], ['Physics', 150, 0], ['Chemistry', 150, 0], ['Biology / Computer', 150, 0]]
            : [['English', 200, 0], ['Urdu', 200, 0], ['Islamic Studies / Ethics', 100, 0], ['Pakistan Studies', 100, 0], ['Elective I', 200, 0], ['Elective II', 200, 0], ['Elective III', 200, 0]];
        $max = array_sum(array_column($subjects, 1));
        $scale = $total / $max;
        $left = $obtained;
        foreach ($subjects as $i => &$s) {
            $s[1] = (int) round($s[1] * $scale);
            $s[2] = $i === count($subjects) - 1 ? $left : min($s[1], (int) round($s[1] * $obtained / $total));
            $left -= $s[2];
        }

        return [
            'name' => $p['name'], 'father_name' => $p['father_name'], 'board' => $p[$lvl.'_board'] ?? null,
            'roll_no' => $p[$lvl.'_roll_no'] ?? null, 'year' => $p[$lvl.'_year'] ?? null, 'group' => $p[$lvl.'_group'] ?? null,
            'obtained' => $obtained, 'total' => $total, 'date_of_birth' => $lvl === 'matric' ? $p['date_of_birth'] : null,
            'subjects' => $subjects,
        ];
    }
}
