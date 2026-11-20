<?php

namespace App\Http\Controllers\Admissions;

use App\Http\Controllers\Controller;
use App\Models\AcademicSession;
use App\Models\ActivityLog;
use App\Models\AdmissionCycle;
use App\Models\Program;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CycleController extends Controller
{
    public function index(): View
    {
        $cycles = AdmissionCycle::with('academicSession')
            ->withCount([
                'applications as submitted_count' => fn ($q) => $q->where('status', '!=', 'draft'),
                'applications as admitted_count' => fn ($q) => $q->where('status', 'admitted'),
                'meritLists',
            ])->orderByDesc('opens_at')->get();

        return view('admissions.cycles.index', ['cycles' => $cycles, 'programs' => Program::pluck('code', 'id')]);
    }

    public function create(): View
    {
        return view('admissions.cycles.form', ['cycle' => new AdmissionCycle([
            'status' => 'draft', 'opens_at' => now()->startOfDay(), 'closes_at' => now()->addWeeks(4)->endOfDay(),
            'academic_session_id' => AcademicSession::current()?->id,
        ])] + $this->options());
    }

    public function store(Request $request): RedirectResponse
    {
        $cycle = AdmissionCycle::create($this->validated($request));
        ActivityLog::record('admissions.cycle_created', "Created admission cycle “{$cycle->title}”", $cycle);

        return redirect()->route('admissions.cycles.index')->with('success', 'Admission cycle created.');
    }

    public function edit(AdmissionCycle $cycle): View
    {
        return view('admissions.cycles.form', ['cycle' => $cycle] + $this->options());
    }

    public function update(Request $request, AdmissionCycle $cycle): RedirectResponse
    {
        $cycle->update($this->validated($request, $cycle));
        ActivityLog::record('admissions.cycle_updated', "Updated admission cycle “{$cycle->title}”", $cycle);

        return redirect()->route('admissions.cycles.index')->with('success', 'Admission cycle saved.');
    }

    private function options(): array
    {
        return [
            'sessions' => AcademicSession::orderByDesc('starts_on')->pluck('name', 'id'),
            'programs' => Program::where('is_active', true)->orderBy('level')->orderBy('name')->get(),
        ];
    }

    private function validated(Request $request, ?AdmissionCycle $cycle = null): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'min:5', 'max:120'],
            'academic_session_id' => ['required', 'exists:academic_sessions,id'],
            'opens_at' => ['required', 'date'],
            'closes_at' => ['required', 'date', 'after:opens_at'],
            'application_fee' => ['required', 'integer', 'min:0', 'max:100000'],
            'status' => ['required', Rule::in(['draft', 'open', 'closed', 'archived'])],
            'program_ids' => ['required', 'array', 'min:1'],
            'program_ids.*' => ['integer', 'exists:programs,id'],
            'instructions' => ['required', 'string', 'min:20', 'max:3000'],
        ], ['program_ids.required' => 'Choose at least one programme offered in this cycle.']);

        if ($data['status'] === 'open') {
            $other = AdmissionCycle::where('status', 'open')->when($cycle, fn ($q) => $q->where('id', '!=', $cycle->id))->first();
            if ($other) {
                abort(back()->withInput()->withErrors(['status' => "“{$other->title}” is already open. Close it first — only one cycle can take applications at a time."]));
            }
        }
        $data['program_ids'] = array_map('intval', $data['program_ids']);

        return $data;
    }
}
