<?php

namespace App\Http\Controllers\Apply;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessApplicationDocuments;
use App\Models\ActivityLog;
use App\Models\AdmissionApplication;
use App\Models\AdmissionCycle;
use App\Models\ApplicationDocument;
use App\Models\Program;
use App\Services\Admissions\CrossDocumentVerifier;
use App\Services\Admissions\Extractors;
use App\Services\Admissions\MeritCalculator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ApplyController extends Controller
{
    public const CNIC_RULE = 'regex:/^\d{5}-?\d{7}-?\d$/';

    public const PHONE_RULE = 'regex:/^(\+92|0)3\d{2}-?\d{7}$/';

    /** Step 1 — programme & contact. */
    public function start(): View
    {
        $cycle = AdmissionCycle::active();

        return view('apply.start', [
            'cycle' => $cycle,
            'open' => $cycle?->isAcceptingApplications() ?? false,
            'programs' => $cycle?->programs() ?? collect(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $cycle = AdmissionCycle::active();
        abort_unless($cycle?->isAcceptingApplications(), 403, 'Admissions are not open right now.');
        $programIds = $cycle->programs()->pluck('id')->all();

        $data = $request->validate([
            'program_id' => ['required', Rule::in($programIds)],
            'second_program_id' => ['nullable', Rule::in($programIds), 'different:program_id'],
            'name' => ['required', 'string', 'min:3', 'max:80'],
            'cnic' => ['required', self::CNIC_RULE],
            'phone' => ['required', self::PHONE_RULE],
            'email' => ['required', 'email', 'max:120'],
            'quota' => ['required', Rule::in(array_keys(AdmissionApplication::QUOTAS))],
            'is_hafiz' => ['nullable', 'boolean'],
        ], [
            'cnic.regex' => 'Enter the 13-digit CNIC / B-Form number, e.g. 37405-1234567-1.',
            'phone.regex' => 'Enter a Pakistani mobile number, e.g. 0300-1234567.',
            'program_id.required' => 'Choose the programme you want to apply for.',
        ]);

        $data['cnic'] = self::formatCnic($data['cnic']);
        $existing = AdmissionApplication::where('admission_cycle_id', $cycle->id)->where('cnic', $data['cnic'])->first();
        if ($existing) {
            return back()->withInput()->withErrors(['cnic' => "An application ({$existing->tracking_no}) already exists for this CNIC / B-Form. Use “Check status” with your PIN to continue it."]);
        }

        $app = AdmissionApplication::create($data + [
            'admission_cycle_id' => $cycle->id,
            'tracking_no' => AdmissionApplication::newTrackingNo(),
            'access_pin' => (string) random_int(100000, 999999),
            'is_hafiz' => $request->boolean('is_hafiz'),
            'status' => 'draft',
            'phone' => self::formatPhone($data['phone']),
        ]);
        self::grant($request, $app);
        session()->flash('pin_notice', true);

        return redirect()->route('apply.documents', $app->tracking_no);
    }

    /** Step 2 — documents, read by AI. */
    public function documents(Request $request, string $tracking): View|RedirectResponse
    {
        $app = $this->accessible($request, $tracking);
        if ($app->status !== 'draft') {
            return redirect()->route('apply.done', $tracking);
        }

        return view('apply.documents', [
            'app' => $app->load('documents', 'program'),
            'required' => $app->requiredDocumentTypes(),
            'aiEnabled' => Extractors::aiEnabled(),
        ]);
    }

    public function upload(Request $request, string $tracking, string $type): JsonResponse|RedirectResponse
    {
        $app = $this->accessible($request, $tracking);
        abort_unless($app->status === 'draft', 409, 'This application has already been submitted.');
        abort_unless(array_key_exists($type, AdmissionApplication::DOCUMENT_TYPES), 404);

        $rules = $type === 'photo'
            ? ['required', 'file', 'mimes:jpg,jpeg,png', 'max:2048', 'dimensions:min_width=200,min_height=200']
            : ['required', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'];
        $request->validate(['file' => $rules], [
            'file.max' => 'File is too large — keep it under '.($type === 'photo' ? '2' : '5').' MB.',
            'file.mimes' => 'Upload a JPG, PNG'.($type === 'photo' ? '' : ', WEBP or PDF').' file.',
        ]);

        $file = $request->file('file');
        $path = $file->storeAs("admissions/{$app->id}", $type.'-'.Str::random(8).'.'.strtolower($file->getClientOriginalExtension() ?: $file->extension()), 'local');

        $old = $app->documents()->where('type', $type)->first();
        if ($old) {
            Storage::disk('local')->delete($old->path);
        }
        $doc = ApplicationDocument::updateOrCreate(
            ['admission_application_id' => $app->id, 'type' => $type],
            [
                'path' => $path, 'original_name' => mb_substr($file->getClientOriginalName(), 0, 180),
                'mime' => $file->getMimeType() ?: 'application/octet-stream', 'size' => $file->getSize(),
                'extraction_status' => 'pending', 'extracted' => null, 'confidence' => null,
                'extraction_error' => null, 'extracted_by' => null, 'extracted_at' => null,
            ],
        );

        ProcessApplicationDocuments::dispatchAfterResponse($app->id);

        if ($request->expectsJson()) {
            return response()->json(['ok' => true, 'document' => $this->docState($doc)]);
        }

        return back()->with('success', $doc->label().' uploaded.');
    }

    /** Lets the applicant see their own upload (private disk, session-gated). */
    public function file(Request $request, string $tracking, string $type)
    {
        $doc = $this->accessible($request, $tracking)->documents()->where('type', $type)->firstOrFail();
        abort_unless(Storage::disk('local')->exists($doc->path), 404);

        return Storage::disk('local')->response($doc->path, null, ['Content-Type' => $doc->mime, 'Cache-Control' => 'private, max-age=300', 'X-Content-Type-Options' => 'nosniff']);
    }

    /** Polled by the documents page while the AI reads uploads. */
    public function progress(Request $request, string $tracking): JsonResponse
    {
        $app = $this->accessible($request, $tracking)->load('documents');
        $docs = $app->documents->mapWithKeys(fn ($d) => [$d->type => $this->docState($d)]);

        return response()->json([
            'documents' => $docs,
            'busy' => $app->documents->contains(fn ($d) => in_array($d->extraction_status, ['pending', 'processing'], true)),
            'complete' => collect($app->requiredDocumentTypes())->every(fn ($t) => $docs->has($t)),
        ]);
    }

    /** Step 3 — review auto-filled details. */
    public function review(Request $request, string $tracking): View|RedirectResponse
    {
        $app = $this->accessible($request, $tracking)->load('documents', 'program', 'checks');
        if ($app->status !== 'draft') {
            return redirect()->route('apply.done', $tracking);
        }
        $missing = array_diff($app->requiredDocumentTypes(), $app->documents->pluck('type')->all());
        if ($missing) {
            return redirect()->route('apply.documents', $tracking)
                ->with('error', 'Please upload: '.implode(', ', array_map(fn ($t) => AdmissionApplication::DOCUMENT_TYPES[$t], $missing)).'.');
        }

        return view('apply.review', [
            'app' => $app,
            'cycle' => $app->cycle,
            'programs' => $app->cycle->programs(),
            'warnings' => $app->checks->whereIn('result', ['mismatch', 'partial'])->whereNotIn('field', ['duplicate_cnic']),
        ]);
    }

    public function submit(Request $request, string $tracking): RedirectResponse
    {
        $app = $this->accessible($request, $tracking)->load('program', 'documents');
        abort_unless($app->status === 'draft', 409, 'This application has already been submitted.');
        abort_unless($app->cycle->isAcceptingApplications(), 403, 'The admission deadline has passed.');

        $programIds = $app->cycle->programs()->pluck('id')->all();
        $year = (int) now()->year;
        $needsInter = (bool) Program::find($request->input('program_id'))?->basedOnInter();
        $marks = fn (string $p, bool $req) => [
            "{$p}_board" => [$req ? 'required' : 'nullable', 'string', 'max:64'],
            "{$p}_roll_no" => [$req ? 'required' : 'nullable', 'string', 'max:24'],
            "{$p}_year" => [$req ? 'required' : 'nullable', 'integer', 'between:'.($year - 12).','.$year],
            "{$p}_group" => [$req ? 'required' : 'nullable', 'string', 'max:32'],
            "{$p}_total" => [$req ? 'required' : 'nullable', 'integer', 'between:100,1500'],
            "{$p}_obtained" => [$req ? 'required' : 'nullable', 'integer', 'min:0', "lte:{$p}_total"],
        ];

        $data = $request->validate([
            'program_id' => ['required', Rule::in($programIds)],
            'second_program_id' => ['nullable', Rule::in($programIds), 'different:program_id'],
            'name' => ['required', 'string', 'min:3', 'max:80'],
            'father_name' => ['required', 'string', 'min:3', 'max:80'],
            'cnic' => ['required', self::CNIC_RULE],
            'father_cnic' => ['required', self::CNIC_RULE, 'different:cnic'],
            'date_of_birth' => ['required', 'date', 'before:-13 years', 'after:-40 years'],
            'gender' => ['required', Rule::in(['male', 'female'])],
            'religion' => ['required', 'string', 'max:24'],
            'phone' => ['required', self::PHONE_RULE],
            'guardian_phone' => ['required', self::PHONE_RULE],
            'email' => ['required', 'email', 'max:120'],
            'address' => ['required', 'string', 'min:8', 'max:200'],
            'city' => ['required', 'string', 'max:64'],
            'domicile' => ['required', 'string', 'max:64'],
            'quota' => ['required', Rule::in(array_keys(AdmissionApplication::QUOTAS))],
            'is_hafiz' => ['nullable', 'boolean'],
            'declaration' => ['accepted'],
            ...$marks('matric', true),
            ...$marks('inter', $needsInter),
        ], [
            'cnic.regex' => 'Enter the 13-digit CNIC / B-Form number, e.g. 37405-1234567-1.',
            'father_cnic.regex' => 'Enter the 13-digit CNIC number, e.g. 37405-1234567-1.',
            'phone.regex' => 'Enter a Pakistani mobile number, e.g. 0300-1234567.',
            'guardian_phone.regex' => 'Enter a Pakistani mobile number, e.g. 0300-1234567.',
            'declaration.accepted' => 'Please confirm the declaration before submitting.',
            'date_of_birth.before' => 'Applicants must be at least 13 years old.',
            '*.lte' => 'Obtained marks cannot be more than total marks.',
        ]);

        unset($data['declaration']);
        $data['cnic'] = self::formatCnic($data['cnic']);
        $data['father_cnic'] = self::formatCnic($data['father_cnic']);
        $data['phone'] = self::formatPhone($data['phone']);
        $data['guardian_phone'] = self::formatPhone($data['guardian_phone']);
        $data['is_hafiz'] = $request->boolean('is_hafiz');
        if (! $needsInter) {
            foreach (['board', 'roll_no', 'year', 'group', 'total', 'obtained'] as $k) {
                $data["inter_{$k}"] = $data["inter_{$k}"] ?? null;
            }
        }

        // Fields the applicant changed are no longer "from documents".
        $auto = collect($app->autofilled_fields ?? [])->filter(fn ($f) => (string) $app->{$f} === (string) ($data[$f] ?? $app->{$f}))->values()->all();

        $app->fill($data + ['status' => 'submitted', 'submitted_at' => now(), 'autofilled_fields' => $auto])->save();
        (new CrossDocumentVerifier)->verify($app->fresh(['documents', 'program']));
        (new MeritCalculator)->apply($app->fresh('program'));
        ActivityLog::record('admissions.submitted', "Application {$app->tracking_no} submitted online by {$app->name}", $app);

        return redirect()->route('apply.done', $tracking);
    }

    public function done(Request $request, string $tracking): View|RedirectResponse
    {
        $app = $this->accessible($request, $tracking)->load('program', 'cycle');
        if ($app->status === 'draft') {
            return redirect()->route('apply.documents', $tracking);
        }

        return view('apply.done', ['app' => $app, 'pin' => $app->access_pin]);
    }

    // ------------------------------------------------------------------

    public static function grant(Request $request, AdmissionApplication $app): void
    {
        $list = $request->session()->get('apply_access', []);
        $list[] = $app->tracking_no;
        $request->session()->put('apply_access', array_values(array_unique($list)));
    }

    private function accessible(Request $request, string $tracking): AdmissionApplication
    {
        if (! in_array($tracking, $request->session()->get('apply_access', []), true)) {
            abort(redirect()->route('apply.status', ['tracking_no' => $tracking])->with('error', 'Enter your PIN to open this application.'));
        }

        return AdmissionApplication::where('tracking_no', $tracking)->firstOrFail();
    }

    private function docState(ApplicationDocument $d): array
    {
        $summary = [];
        if ($d->extraction_status === 'done' && is_array($d->extracted)) {
            foreach (['name', 'father_name', 'cnic_number', 'date_of_birth', 'roll_no', 'obtained', 'total', 'board', 'district'] as $k) {
                if (filled($d->extracted[$k] ?? null)) {
                    $summary[str_replace('_', ' ', $k)] = (string) $d->extracted[$k];
                }
            }
        }

        return [
            'type' => $d->type,
            'status' => $d->extraction_status,
            'name' => $d->original_name,
            'size' => $d->size,
            'confidence' => $d->confidence,
            'error' => $d->extraction_error,
            'summary' => $summary,
            'issues' => $d->extracted['issues'] ?? [],
            'preview' => str_starts_with($d->mime, 'image/') ? route('apply.file', [$d->application->tracking_no, $d->type]).'?v='.$d->updated_at?->timestamp : null,
        ];
    }

    public static function formatCnic(string $v): string
    {
        $d = preg_replace('/\D/', '', $v);

        return strlen($d) === 13 ? substr($d, 0, 5).'-'.substr($d, 5, 7).'-'.substr($d, 12) : $v;
    }

    public static function formatPhone(string $v): string
    {
        $d = preg_replace('/\D/', '', $v);
        if (str_starts_with($d, '92')) {
            $d = '0'.substr($d, 2);
        }

        return strlen($d) === 11 ? substr($d, 0, 4).'-'.substr($d, 4) : $v;
    }
}
