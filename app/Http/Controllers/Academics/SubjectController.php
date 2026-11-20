<?php

namespace App\Http\Controllers\Academics;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Department;
use App\Models\Subject;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SubjectController extends Controller
{
    public function index(Request $request): View
    {
        $subjects = Subject::with(['department', 'courses.section.program', 'courses.teacher'])
            ->withCount('courses')
            ->when($request->filled('department'), fn ($q) => $q->where('department_id', $request->integer('department')))
            ->when($request->filled('q'), fn ($q) => $q->where(fn ($w) => $w->where('name', 'like', '%'.$request->q.'%')->orWhere('code', 'like', '%'.$request->q.'%')))
            ->orderBy('code')
            ->get();

        return view('academics.subjects.index', [
            'subjects' => $subjects,
            'departments' => Department::orderBy('name')->pluck('name', 'id'),
        ]);
    }

    public function create(): View
    {
        return view('academics.subjects.form', ['subject' => new Subject(['credit_hours' => 3]), 'departments' => Department::orderBy('name')->pluck('name', 'id')]);
    }

    public function store(Request $request): RedirectResponse
    {
        $subject = Subject::create($this->validated($request));
        ActivityLog::record('academics.subject_created', "Added subject {$subject->code} {$subject->name}", $subject);

        return redirect()->route('academics.subjects.index')->with('success', "{$subject->name} added.");
    }

    public function edit(Subject $subject): View
    {
        return view('academics.subjects.form', ['subject' => $subject, 'departments' => Department::orderBy('name')->pluck('name', 'id')]);
    }

    public function update(Request $request, Subject $subject): RedirectResponse
    {
        $subject->update($this->validated($request, $subject));
        ActivityLog::record('academics.subject_updated', "Updated subject {$subject->code}", $subject);

        return redirect()->route('academics.subjects.index')->with('success', 'Subject updated.');
    }

    private function validated(Request $request, ?Subject $subject = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'code' => ['required', 'string', 'max:24', 'regex:/^[A-Z0-9-]+$/', Rule::unique('subjects', 'code')->ignore($subject?->id)],
            'department_id' => ['required', 'exists:departments,id'],
            'credit_hours' => ['required', 'integer', 'between:1,6'],
            'has_practical' => ['nullable', 'boolean'],
        ], ['code.regex' => 'Use capital letters, digits and dashes (e.g. CS-101).']);
        $data['has_practical'] = $request->boolean('has_practical');

        return $data;
    }
}
