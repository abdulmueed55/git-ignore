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
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ApplyController extends Controller
{
    public const CNIC_RULE = 'regex:/^\d{5}-?\d{7}-?\d$/';

    public const PHONE_RULE = 'regex:/^(\+92|0)3\d{2}-?\d{7}$/';

    /** Letters, spaces and . ' - only (also keeps spreadsheet formulas out of exports). */
    public const NAME_RULE = 'regex:/^[\pL\pM][\pL\pM\s.\'\-]*$/u';

    public const PLACE_RULE = 'regex:/^[\pL\pM][\pL\pM\s.,\'\-()]*$/u';

    public const TEXT_RULE = 'regex:/^[^=+@\-\t\r|]/u';

    public const DUPLICATE_MESSAGE = 'An application has already been submitted for this CNIC / B-Form in this admission cycle. Use “Check status” with your tracking number and PIN, or contact the admissions office.';

    /** Uploads allowed per application per hour (each one may cost an AI reading). */
    public const UPLOADS_PER_HOUR = 20;

    public const PDF_MAX_PAGES = 4;

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
            'name' => ['required', 'string', 'min:3', 'max:80', self::NAME_RULE],
            'cnic' => ['required', self::CNIC_RULE],
            'phone' => ['required', self::PHONE_RULE],
            'email' => ['required', 'email:rfc,strict,filter', 'max:120'],
            'quota' => ['required', Rule::in(array_keys(AdmissionApplication::QUOTAS))],
            'is_hafiz' => ['nullable', 'boolean'],
        ], [
            'cnic.regex' => 'Enter the 13-digit CNIC / B-Form number, e.g. 37405-1234567-1.',
            'phone.regex' => 'Enter a Pakistani mobile number, e.g. 0300-1234567.',
            'name.regex' => 'Use letters only, as written on your CNIC / B-Form.',
            'program_id.required' => 'Choose the programme you want to apply for.',
        ]);

        $data['cnic'] = self::formatCnic($data['cnic']);
        // Only a *submitted* application blocks a CNIC, so nobody can lock out a real
        // applicant by opening a draft with their number. Never reveal its tracking number.
        if (self::submittedFor($cycle->id, $data['cnic'])) {
            return back()->withInput()->withErrors(['cnic' => self::DUPLICATE_MESSAGE]);
        }

        $pin = (string) random_int(100000, 999999);
        $app = AdmissionApplication::create($data + [
            'admission_cycle_id' => $cycle->id,
            'tracking_no' => AdmissionApplication::newTrackingNo(),
            'access_pin' => $pin,
            'is_hafiz' => $request->boolean('is_hafiz'),
            'status' => 'draft',
            'phone' => self::formatPhone($data['phone']),
        ]);
        self::grant($request, $app, $pin);
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
        if ($file->extension() === 'pdf' && self::pdfPages($file->getRealPath()) > self::PDF_MAX_PAGES) {
            return $this->uploadError($request, 'Upload only the pages of this document (at most '.self::PDF_MAX_PAGES.' pages), or a photo of it.');
        }
        $limiter = 'apply-upload:'.$app->id;
        if (RateLimiter::tooManyAttempts($limiter, self::UPLOADS_PER_HOUR)) {
            return $this->uploadError($request, 'Too many uploads for this application — try again in '.ceil(RateLimiter::availableIn($limiter) / 60).' minutes.', 429);
        }
        RateLimiter::hit($limiter, 3600);

        // The extension comes from the file's content, never from the name the browser sent.
        $path = $file->storeAs("admissions/{$app->id}", $type.'-'.Str::random(16).'.'.$file->extension(), 'local');

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

    private function uploadError(Request $request, string $message, int $status = 422): JsonResponse|RedirectResponse
    {
        return $request->expectsJson()
            ? response()->json(['message' => $message, 'errors' => ['file' => [$message]]], $status)
            : back()->withErrors(['file' => $message]);
    }

    /** Rough page count of a PDF (counts /Type /Page objects). */
    public static function pdfPages(string $path): int
    {
        $raw = @file_get_contents($path, false, null, 0, 6 * 1024 * 1024);

        return $raw === false ? 0 : preg_match_all('#/Type\s*/Page(?![a-zA-Z])#', $raw);
    }

    /** Lets the applicant see their own upload (private disk, session-gated). */
    public function file(Request $request, string $tracking, string $type)
    {
        $doc = $this->accessible($request, $tracking)->documents()->where('type', $type)->firstOrFail();
        abort_unless(Storage::disk('local')->exists($doc->path), 404);

        return Storage::disk('local')->response($doc->path, null, [
            'Content-Type' => $doc->mime,
            'Cache-Control' => 'private, max-age=300',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "sandbox; default-src 'none'; img-src 'self' data:; style-src 'unsafe-inline'",
        ]);
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
            "{$p}_board" => [$req ? 'required' : 'nullable', 'string', 'max:64', self::PLACE_RULE],
            "{$p}_roll_no" => [$req ? 'required' : 'nullable', 'string', 'max:24', 'regex:/^[A-Za-z0-9][A-Za-z0-9\/\-]*$/'],
            "{$p}_year" => [$req ? 'required' : 'nullable', 'integer', 'between:'.($year - 12).','.$year],
            "{$p}_group" => [$req ? 'required' : 'nullable', 'string', 'max:32', self::PLACE_RULE],
            "{$p}_total" => [$req ? 'required' : 'nullable', 'integer', 'between:100,1500'],
            "{$p}_obtained" => [$req ? 'required' : 'nullable', 'integer', 'min:0', "lte:{$p}_total"],
        ];

        $data = $request->validate([
            'program_id' => ['required', Rule::in($programIds)],
            'second_program_id' => ['nullable', Rule::in($programIds), 'different:program_id'],
            'name' => ['required', 'string', 'min:3', 'max:80', self::NAME_RULE],
            'father_name' => ['required', 'string', 'min:3', 'max:80', self::NAME_RULE],
            'cnic' => ['required', self::CNIC_RULE],
            'father_cnic' => ['required', self::CNIC_RULE, 'different:cnic'],
            'date_of_birth' => ['required', 'date', 'before:-13 years', 'after:-40 years'],
            'gender' => ['required', Rule::in(['male', 'female'])],
            'religion' => ['required', 'string', 'max:24', self::NAME_RULE],
            'phone' => ['required', self::PHONE_RULE],
            'guardian_phone' => ['required', self::PHONE_RULE],
            'email' => ['required', 'email:rfc,strict,filter', 'max:120'],
            'address' => ['required', 'string', 'min:8', 'max:200', self::TEXT_RULE],
            'city' => ['required', 'string', 'max:64', self::PLACE_RULE],
            'domicile' => ['required', 'string', 'max:64', self::PLACE_RULE],
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
            'name.regex' => 'Use letters only, as written on your CNIC / B-Form.',
            'father_name.regex' => 'Use letters only, as written on the CNIC.',
            'city.regex' => 'Use letters only.',
            'domicile.regex' => 'Use letters only.',
            'religion.regex' => 'Use letters only.',
            'address.regex' => 'The address cannot start with = + - @ or |.',
            '*_board.regex' => 'Write the board name in letters, e.g. BISE Rawalpindi.',
            '*_group.regex' => 'Write the group in letters, e.g. Pre-Medical.',
            '*_roll_no.regex' => 'Roll numbers use letters, digits, / and - only.',
        ]);

        unset($data['declaration']);
        $data['cnic'] = self::formatCnic($data['cnic']);
        if (self::submittedFor($app->admission_cycle_id, $data['cnic'], $app->id)) {
            return back()->withInput()->withErrors(['cnic' => self::DUPLICATE_MESSAGE]);
        }
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

        return view('apply.done', ['app' => $app, 'pin' => self::knownPin($request, $app)]);
    }

    // ------------------------------------------------------------------

    /** Opens the application for this browser; the plain PIN is kept only in this session. */
    public static function grant(Request $request, AdmissionApplication $app, ?string $pin = null): void
    {
        $request->session()->regenerate();
        $list = $request->session()->get('apply_access', []);
        $list[] = $app->tracking_no;
        $request->session()->put('apply_access', array_values(array_unique(array_slice($list, -10))));
        if ($pin !== null) {
            $request->session()->put('apply_pins', array_slice([$app->tracking_no => $pin] + $request->session()->get('apply_pins', []), 0, 10, true));
        }
    }

    public static function knownPin(Request $request, AdmissionApplication $app): ?string
    {
        return $request->session()->get('apply_pins', [])[$app->tracking_no] ?? null;
    }

    private static function submittedFor(int $cycleId, string $cnic, ?int $exceptId = null): bool
    {
        return AdmissionApplication::where('admission_cycle_id', $cycleId)->where('cnic', $cnic)
            ->where('status', '!=', 'draft')->when($exceptId, fn ($q) => $q->whereKeyNot($exceptId))->exists();
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
