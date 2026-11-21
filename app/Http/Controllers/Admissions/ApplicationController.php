<?php

namespace App\Http\Controllers\Admissions;

use App\Http\Controllers\Apply\ApplyController;
use App\Http\Controllers\Controller;
use App\Jobs\ProcessApplicationDocuments;
use App\Models\ActivityLog;
use App\Models\AdmissionApplication;
use App\Models\AdmissionCycle;
use App\Models\MeritListEntry;
use App\Models\Program;
use App\Models\VerificationCheck;
use App\Services\Admissions\Admitter;
use App\Services\Admissions\CrossDocumentVerifier;
use App\Services\Admissions\FieldMap;
use App\Services\Admissions\MeritCalculator;
use App\Support\Csv;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ApplicationController extends Controller
{
    private function filtered(Request $request): Builder
    {
        $q = AdmissionApplication::query()->with(['program', 'cycle']);
        $cycleId = $request->query('cycle', AdmissionCycle::active()?->id);
        if ($cycleId && $cycleId !== 'all') {
            $q->where('admission_cycle_id', $cycleId);
        }
        foreach (['program_id' => 'program', 'status' => 'status', 'verification_status' => 'verification', 'quota' => 'quota'] as $col => $param) {
            if (filled($request->query($param))) {
                $q->where($col, $request->query($param));
            }
        }
        if (! filled($request->query('status'))) {
            $q->where('status', '!=', 'draft');
        }
        if ($s = trim((string) $request->query('q'))) {
            $digits = preg_replace('/\D/', '', $s);
            $q->where(function ($w) use ($s, $digits) {
                $w->where('name', 'like', "%{$s}%")
                    ->orWhere('father_name', 'like', "%{$s}%")
                    ->orWhere('tracking_no', 'like', '%'.strtoupper($s).'%')
                    ->orWhere('phone', 'like', "%{$s}%");
                if (strlen($digits) >= 5) {
                    $w->orWhereRaw("REPLACE(cnic, '-', '') LIKE ?", ["%{$digits}%"]);
                }
            });
        }
        $sort = $request->query('sort', 'recent');
        match ($sort) {
            'merit' => $q->orderByDesc('merit_score'),
            'merit_asc' => $q->orderBy('merit_score'),
            'score' => $q->orderBy('verification_score'),
            'name' => $q->orderBy('name'),
            default => $q->orderByDesc('submitted_at')->orderByDesc('id'),
        };

        return $q;
    }

    public function index(Request $request): View
    {
        $apps = $this->filtered($request)->paginate(20)->withQueryString();

        return view('admissions.index', [
            'apps' => $apps,
            'cycles' => AdmissionCycle::orderByDesc('opens_at')->get(),
            'programs' => Program::orderBy('name')->get(),
            'currentCycle' => $request->query('cycle', AdmissionCycle::active()?->id),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $rows = $this->filtered($request)->get();
        $name = 'applications-'.now()->format('Ymd-His').'.csv';
        ActivityLog::record('admissions.exported', "Exported {$rows->count()} applications to CSV (includes CNIC and phone numbers)");

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // Excel-friendly UTF-8
            fputcsv($out, ['Tracking no', 'Name', 'Father name', 'CNIC/B-Form', 'Program', 'Quota', 'Hafiz', 'Phone', 'Email', 'City', 'Domicile', 'Matric', 'Inter', 'Merit', 'Verification', 'Score', 'Status', 'Submitted', 'Auto-filled fields']);
            foreach ($rows as $a) {
                fputcsv($out, Csv::row([
                    $a->tracking_no, $a->name, $a->father_name, $a->cnic, $a->program?->code, $a->quota, $a->is_hafiz ? 'Yes' : 'No',
                    $a->phone, $a->email, $a->city, $a->domicile,
                    $a->matric_total ? "{$a->matric_obtained}/{$a->matric_total}" : '',
                    $a->inter_total ? "{$a->inter_obtained}/{$a->inter_total}" : '',
                    $a->merit_score, $a->verification_status, $a->verification_score, $a->statusLabel(),
                    $a->submitted_at?->format('Y-m-d H:i'), implode(' ', $a->autofilled_fields ?? []),
                ]));
            }
            fclose($out);
        }, $name, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function show(AdmissionApplication $application): View
    {
        $application->load(['program', 'secondProgram', 'cycle', 'documents', 'checks', 'reviewer', 'student.section.program']);
        $merit = (new MeritCalculator)->breakdown($application);
        $entry = MeritListEntry::with('meritList')->where('admission_application_id', $application->id)->latest()->first();
        $timeline = ActivityLog::with('user')->where('subject_type', $application->getMorphClass())->where('subject_id', $application->id)->latest()->take(15)->get();

        $docs = $application->documents->keyBy('type');
        $compareDocs = collect(FieldMap::COMPARE_DOCS)->filter(fn ($t) => $docs->has($t) || in_array($t, $application->requiredDocumentTypes(), true))->values();
        $checks = $application->checks->keyBy('field');

        return view('admissions.show', [
            'app' => $application,
            'merit' => $merit,
            'entry' => $entry,
            'timeline' => $timeline,
            'docs' => $docs,
            'compareDocs' => $compareDocs,
            'checks' => $checks,
            'fieldMap' => FieldMap::FIELDS,
            'programs' => Program::orderBy('name')->get(),
            'needsInter' => $application->program?->basedOnInter() || $docs->has('inter_dmc'),
        ]);
    }

    /** Polled while documents are being re-read. */
    public function progress(AdmissionApplication $application): JsonResponse
    {
        $application->load('documents');

        return response()->json([
            'busy' => $application->documents->contains(fn ($d) => in_array($d->extraction_status, ['pending', 'processing'], true)),
            'documents' => $application->documents->mapWithKeys(fn ($d) => [$d->type => $d->extraction_status]),
            'verification' => $application->verification_status,
            'score' => $application->verification_score,
        ]);
    }

    public function update(Request $request, AdmissionApplication $application): RedirectResponse
    {
        $cnic = ApplyController::CNIC_RULE;
        $data = $request->validate([
            'program_id' => ['required', 'exists:programs,id'],
            'name' => ['required', 'string', 'min:3', 'max:80'],
            'father_name' => ['required', 'string', 'min:3', 'max:80'],
            'cnic' => ['required', $cnic],
            'father_cnic' => ['required', $cnic],
            'date_of_birth' => ['required', 'date'],
            'gender' => ['required', Rule::in(['male', 'female'])],
            'phone' => ['required', 'string', 'max:20'],
            'guardian_phone' => ['required', 'string', 'max:20'],
            'domicile' => ['required', 'string', 'max:64'],
            'quota' => ['required', Rule::in(array_keys(AdmissionApplication::QUOTAS))],
            'is_hafiz' => ['nullable', 'boolean'],
            'matric_board' => ['required', 'string', 'max:64'],
            'matric_roll_no' => ['required', 'string', 'max:24'],
            'matric_year' => ['required', 'integer', 'between:2000,2100'],
            'matric_total' => ['required', 'integer', 'between:100,1500'],
            'matric_obtained' => ['required', 'integer', 'min:0', 'lte:matric_total'],
            'inter_board' => ['nullable', 'string', 'max:64'],
            'inter_roll_no' => ['nullable', 'string', 'max:24'],
            'inter_year' => ['nullable', 'integer', 'between:2000,2100'],
            'inter_total' => ['nullable', 'required_with:inter_obtained', 'integer', 'between:100,1500'],
            'inter_obtained' => ['nullable', 'integer', 'min:0', 'lte:inter_total'],
            'reason' => ['required', 'string', 'min:5', 'max:200'],
        ], ['reason.required' => 'Write a short reason for the correction — it goes into the audit trail.']);

        $reason = $data['reason'];
        unset($data['reason']);
        $data['is_hafiz'] = $request->boolean('is_hafiz');
        $data['cnic'] = ApplyController::formatCnic($data['cnic']);
        $data['father_cnic'] = ApplyController::formatCnic($data['father_cnic']);

        $application->fill($data);
        $changed = array_keys($application->getDirty());
        $application->autofilled_fields = array_values(array_diff($application->autofilled_fields ?? [], $changed));
        $application->save();

        (new CrossDocumentVerifier)->verify($application->fresh(['documents', 'program']));
        (new MeritCalculator)->apply($application->fresh('program'));
        ActivityLog::record('admissions.corrected', 'Corrected '.($changed ? implode(', ', $changed) : 'no fields').": {$reason}", $application, ['fields' => $changed]);

        return back()->with('success', $changed ? 'Details corrected and re-verified.' : 'No changes — verification refreshed.');
    }

    public function status(Request $request, AdmissionApplication $application): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(['under_review', 'verified', 'flagged', 'waitlisted', 'rejected'])],
            'remarks' => [Rule::requiredIf(in_array($request->input('status'), ['rejected', 'flagged'], true)), 'nullable', 'string', 'max:500'],
        ], ['remarks.required' => 'Give a reason — the applicant sees it on their status page.']);

        abort_if($application->status === 'admitted', 422, 'Admitted applicants cannot change status here.');
        abort_if($application->status === 'draft', 422, 'This application has not been submitted yet.');
        $application->update($data + ['reviewed_by' => $request->user()->id, 'reviewed_at' => now()]);
        ActivityLog::record('admissions.status', "Marked {$application->tracking_no} as ".AdmissionApplication::STATUSES[$data['status']].($data['remarks'] ?? null ? " — {$data['remarks']}" : ''), $application);

        return back()->with('success', 'Status updated to '.AdmissionApplication::STATUSES[$data['status']].'.');
    }

    public function reverify(AdmissionApplication $application): RedirectResponse
    {
        $application->documents()->where('type', '!=', 'photo')->update(['extraction_status' => 'pending']);
        ProcessApplicationDocuments::dispatchAfterResponse($application->id, true);
        ActivityLog::record('admissions.reverify', "Re-ran AI reading for {$application->tracking_no}", $application);

        return back()->with('success', 'Documents queued for AI reading — results appear in a few seconds.');
    }

    public function admit(AdmissionApplication $application, Admitter $admitter): RedirectResponse
    {
        try {
            $student = $admitter->admit($application);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "Admitted — roll no {$student->roll_no}".($student->section ? ', '.$student->section->label() : '').'.');
    }

    public function override(Request $request, AdmissionApplication $application, VerificationCheck $check): RedirectResponse
    {
        abort_unless($check->admission_application_id === $application->id, 404);
        $data = $request->validate(['note' => ['required', 'string', 'min:5', 'max:250']], ['note.required' => 'Explain why this difference is acceptable.']);

        $check->update(['overridden' => true, 'overridden_by' => $request->user()->id, 'note' => $data['note']]);
        (new CrossDocumentVerifier)->verify($application->fresh(['documents', 'program']));
        ActivityLog::record('admissions.override', "Accepted “{$check->label}” for {$application->tracking_no}: {$data['note']}", $application);

        return back()->with('success', "“{$check->label}” accepted. Verification recalculated.");
    }
}
