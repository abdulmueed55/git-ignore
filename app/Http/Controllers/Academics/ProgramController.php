<?php

namespace App\Http\Controllers\Academics;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Department;
use App\Models\Program;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ProgramController extends Controller
{
    public function index(): View
    {
        $programs = Program::with('department')
            ->withCount(['sections', 'students' => fn ($q) => $q->where('status', 'active')])
            ->orderByRaw("case level when 'intermediate' then 1 when 'adp' then 2 else 3 end")
            ->orderBy('name')
            ->get();

        return view('academics.programs.index', [
            'programs' => $programs,
            'totals' => [
                'programs' => $programs->where('is_active', true)->count(),
                'seats' => $programs->sum('seats'),
                'enrolled' => $programs->sum('students_count'),
            ],
        ]);
    }

    public function show(Program $program): View
    {
        $program->load(['department', 'sections' => fn ($q) => $q->with(['academicSession', 'incharge'])->withCount(['students', 'courses'])->orderBy('year')]);
        $program->loadCount(['students' => fn ($q) => $q->where('status', 'active')]);

        return view('academics.programs.show', compact('program'));
    }

    public function create(): View
    {
        return view('academics.programs.form', $this->formData(new Program([
            'level' => 'intermediate', 'duration_years' => 2, 'seats' => 60, 'shift' => 'morning', 'min_percentage' => 45, 'is_active' => true,
            'merit_weights' => ['academic' => 1, 'matric' => 0, 'hafiz_bonus' => 20],
        ])));
    }

    public function store(Request $request): RedirectResponse
    {
        $program = Program::create($this->validated($request));
        ActivityLog::record('academics.program_created', "Created program {$program->name}", $program);

        return redirect()->route('academics.programs.show', $program)->with('success', "{$program->name} created.");
    }

    public function edit(Program $program): View
    {
        return view('academics.programs.form', $this->formData($program));
    }

    public function update(Request $request, Program $program): RedirectResponse
    {
        $program->update($this->validated($request, $program));
        ActivityLog::record('academics.program_updated', "Updated program {$program->name}", $program);

        return redirect()->route('academics.programs.show', $program)->with('success', 'Program updated.');
    }

    private function formData(Program $program): array
    {
        return ['program' => $program, 'departments' => Department::orderBy('name')->pluck('name', 'id')];
    }

    private function validated(Request $request, ?Program $program = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'code' => ['required', 'string', 'max:24', 'regex:/^[A-Z0-9-]+$/', Rule::unique('programs', 'code')->ignore($program?->id)],
            'department_id' => ['required', 'exists:departments,id'],
            'level' => ['required', Rule::in(array_keys(Program::LEVELS))],
            'duration_years' => ['required', 'integer', 'between:1,5'],
            'seats' => ['required', 'integer', 'between:1,1000'],
            'shift' => ['required', Rule::in(['morning', 'evening'])],
            'eligibility' => ['required', 'string', 'max:255'],
            'min_percentage' => ['required', 'numeric', 'between:0,100'],
            'annual_fee' => ['required', 'integer', 'min:0', 'max:2000000'],
            'weights.academic' => ['required', 'numeric', 'between:0,1'],
            'weights.matric' => ['required', 'numeric', 'between:0,1'],
            'weights.hafiz_bonus' => ['required', 'integer', 'between:0,50'],
            'is_active' => ['nullable', 'boolean'],
        ], ['code.regex' => 'Use capital letters, digits and dashes (e.g. FSC-PM).']);

        $w = $data['weights'];
        $total = round((float) $w['academic'] + (float) $w['matric'], 2);
        if ($data['level'] !== 'intermediate' && $total !== 1.0) {
            throw \Illuminate\Validation\ValidationException::withMessages(['weights.academic' => "Intermediate and Matric weights must add up to 1.0 (currently {$total})."]);
        }
        if ($data['level'] === 'intermediate') {
            $w['academic'] = 1;
            $w['matric'] = 0;
        }

        unset($data['weights']);
        $data['merit_weights'] = ['academic' => (float) $w['academic'], 'matric' => (float) $w['matric'], 'hafiz_bonus' => (int) $w['hafiz_bonus']];
        $data['is_active'] = $request->boolean('is_active');

        return $data;
    }
}
