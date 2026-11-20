<?php

namespace App\Http\Controllers\Academics;

use App\Http\Controllers\Controller;
use App\Models\AcademicSession;
use App\Models\ActivityLog;
use App\Models\Program;
use App\Models\Section;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SectionController extends Controller
{
    public function index(Request $request): View
    {
        $sections = Section::with(['program', 'academicSession', 'incharge'])
            ->withCount(['students' => fn ($q) => $q->where('status', 'active'), 'courses'])
            ->when($request->filled('program'), fn ($q) => $q->where('program_id', $request->integer('program')))
            ->when($request->filled('session'), fn ($q) => $q->where('academic_session_id', $request->integer('session')))
            ->orderBy('program_id')->orderBy('year')->orderBy('name')
            ->get();

        return view('academics.sections.index', [
            'groups' => $sections->groupBy(fn ($s) => $s->program->name),
            'count' => $sections->count(),
            'programs' => Program::orderBy('name')->pluck('name', 'id'),
            'sessions' => AcademicSession::orderByDesc('starts_on')->pluck('name', 'id'),
        ]);
    }

    public function show(Section $section): View
    {
        $section->load(['program', 'academicSession', 'incharge', 'courses.subject.department', 'courses.teacher', 'courses.slots']);
        $students = $section->students()->orderBy('roll_no')->get();

        return view('academics.sections.show', compact('section', 'students'));
    }

    public function create(): View
    {
        return view('academics.sections.form', $this->formData(new Section(['capacity' => 45, 'year' => 1, 'academic_session_id' => AcademicSession::current()?->id])));
    }

    public function store(Request $request): RedirectResponse
    {
        $section = Section::create($this->validated($request));
        ActivityLog::record('academics.section_created', 'Created section '.$section->load('program')->label(), $section);

        return redirect()->route('academics.sections.show', $section)->with('success', 'Section created. Add its courses from the timetable.');
    }

    public function edit(Section $section): View
    {
        return view('academics.sections.form', $this->formData($section));
    }

    public function update(Request $request, Section $section): RedirectResponse
    {
        $section->update($this->validated($request, $section));
        ActivityLog::record('academics.section_updated', 'Updated section '.$section->load('program')->label(), $section);

        return redirect()->route('academics.sections.show', $section)->with('success', 'Section updated.');
    }

    private function formData(Section $section): array
    {
        return [
            'section' => $section,
            'programs' => Program::orderBy('name')->pluck('name', 'id'),
            'sessions' => AcademicSession::orderByDesc('starts_on')->pluck('name', 'id'),
            'teachers' => User::where('role', 'teacher')->where('is_active', true)->orderBy('name')->pluck('name', 'id'),
        ];
    }

    private function validated(Request $request, ?Section $section = null): array
    {
        $data = $request->validate([
            'program_id' => ['required', 'exists:programs,id'],
            'academic_session_id' => ['required', 'exists:academic_sessions,id'],
            'name' => ['required', 'string', 'max:60', Rule::unique('sections')->where(fn ($q) => $q
                ->where('program_id', $request->integer('program_id'))
                ->where('academic_session_id', $request->integer('academic_session_id')))->ignore($section?->id)],
            'year' => ['required', 'integer', 'between:1,5'],
            'room' => ['required', 'string', 'max:32'],
            'capacity' => ['required', 'integer', 'between:5,200'],
            'incharge_id' => ['required', Rule::exists('users', 'id')->where('role', 'teacher')],
        ], ['name.unique' => 'A section with this name already exists for this program and session.']);

        if ($section && $data['capacity'] < $section->students()->count()) {
            throw \Illuminate\Validation\ValidationException::withMessages(['capacity' => 'Capacity cannot be lower than the '.$section->students()->count().' students already enrolled.']);
        }

        return $data;
    }
}
