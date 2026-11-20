<?php

namespace App\Http\Controllers\Academics;

use App\Http\Controllers\Controller;
use App\Models\AcademicSession;
use App\Models\ActivityLog;
use App\Models\Program;
use App\Models\Section;
use App\Models\Student;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StudentController extends Controller
{
    public const STATUSES = ['active' => 'Active', 'freezed' => 'Freezed', 'struck_off' => 'Struck off', 'graduated' => 'Graduated'];

    public const BLOOD_GROUPS = ['A+', 'A-', 'B+', 'B-', 'O+', 'O-', 'AB+', 'AB-'];

    public function index(Request $request): View
    {
        $user = $request->user();
        $base = Student::visibleTo($user);

        $query = $this->filtered($request)
            ->with(['program', 'section'])
            ->orderBy('roll_no');

        $statusCounts = (clone $base)->selectRaw('status, count(*) c')->groupBy('status')->pluck('c', 'status');

        $sectionsQuery = Section::with('program')->orderBy('program_id')->orderBy('year');
        if ($user->role === 'teacher') {
            $sectionsQuery->whereIn('id', (clone $base)->select('section_id'));
        }

        return view('students.index', [
            'students' => $query->paginate(25)->withQueryString(),
            'statusCounts' => $statusCounts,
            'total' => (int) $statusCounts->sum(),
            'programs' => Program::orderBy('name')->pluck('name', 'id'),
            'sections' => $sectionsQuery->get()->mapWithKeys(fn ($s) => [$s->id => $s->label()]),
            'sessions' => AcademicSession::orderByDesc('starts_on')->pluck('name', 'id'),
            'canEdit' => $user->hasRole('admin', 'principal', 'admission_officer'),
            'filters' => $request->only(['q', 'program', 'section', 'session', 'status', 'gender']),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $students = $this->filtered($request)->with(['program', 'section', 'academicSession'])->orderBy('roll_no')->get();
        ActivityLog::record('students.exported', 'Exported '.$students->count().' student records to CSV');

        return response()->streamDownload(function () use ($students) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Roll no', 'Registration no', 'Name', 'Father name', 'CNIC / B-Form', 'Gender', 'Date of birth', 'Program', 'Section', 'Session', 'Phone', 'Guardian phone', 'Email', 'City', 'Domicile', 'Status']);
            foreach ($students as $s) {
                fputcsv($out, [
                    $s->roll_no, $s->registration_no, $s->name, $s->father_name, $s->cnic, ucfirst($s->gender),
                    $s->date_of_birth?->format('Y-m-d'), $s->program?->code, $s->section?->name, $s->academicSession?->name,
                    $s->phone, $s->guardian_phone, $s->email, $s->city, $s->domicile, self::STATUSES[$s->status] ?? $s->status,
                ]);
            }
            fclose($out);
        }, 'students-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv']);
    }

    public function show(Request $request, Student $student): View
    {
        $this->authorizeView($request, $student);
        $student->load(['program.department', 'section.incharge', 'section.courses.subject', 'section.courses.teacher', 'academicSession']);

        $attendance = $student->attendanceSummary();
        $bySubject = collect();
        if ($attendance) {
            $bySubject = $student->attendance()
                ->join('lectures', 'lectures.id', '=', 'attendance_records.lecture_id')
                ->join('courses', 'courses.id', '=', 'lectures.course_id')
                ->join('subjects', 'subjects.id', '=', 'courses.subject_id')
                ->selectRaw("subjects.name as subject, count(*) as total, sum(case when attendance_records.status in ('P','L') then 1 else 0 end) as attended")
                ->groupBy('subjects.name')
                ->orderBy('subjects.name')
                ->get()
                ->map(fn ($r) => ['subject' => $r->subject, 'total' => (int) $r->total, 'pct' => $r->total ? round($r->attended / $r->total * 100, 1) : 0]);
        }

        $activity = ActivityLog::with('user')
            ->where('subject_type', $student->getMorphClass())->where('subject_id', $student->id)
            ->latest()->take(8)->get();

        return view('students.show', [
            'student' => $student,
            'attendance' => $attendance,
            'bySubject' => $bySubject,
            'activity' => $activity,
            'canEdit' => $request->user()->hasRole('admin', 'principal', 'admission_officer'),
        ]);
    }

    public function card(Request $request, Student $student): View
    {
        $this->authorizeView($request, $student);
        $student->load(['program', 'section', 'academicSession']);

        return view('students.card', compact('student'));
    }

    public function create(): View
    {
        return view('students.form', $this->formData(new Student(['status' => 'active', 'gender' => 'male', 'academic_session_id' => AcademicSession::current()?->id])));
    }

    public function store(Request $request): RedirectResponse
    {
        $student = Student::create($this->validated($request));
        ActivityLog::record('students.created', "Enrolled {$student->name} ({$student->roll_no})", $student);

        return redirect()->route('students.show', $student)->with('success', "{$student->name} has been enrolled.");
    }

    public function edit(Student $student): View
    {
        return view('students.form', $this->formData($student));
    }

    public function update(Request $request, Student $student): RedirectResponse
    {
        $data = $this->validated($request, $student);
        $student->fill($data);
        $changed = array_keys($student->getDirty());
        $student->save();
        ActivityLog::record('students.updated', "Updated record of {$student->name} ({$student->roll_no})", $student, ['changed' => $changed]);

        return redirect()->route('students.show', $student)->with('success', 'Student record updated.');
    }

    // ------------------------------------------------------------------

    private function filtered(Request $request)
    {
        return Student::visibleTo($request->user())
            ->search($request->input('q'))
            ->when($request->filled('program'), fn ($q) => $q->where('program_id', $request->integer('program')))
            ->when($request->filled('section'), fn ($q) => $q->where('section_id', $request->integer('section')))
            ->when($request->filled('session'), fn ($q) => $q->where('academic_session_id', $request->integer('session')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
            ->when($request->filled('gender'), fn ($q) => $q->where('gender', $request->input('gender')));
    }

    private function authorizeView(Request $request, Student $student): void
    {
        abort_unless(Student::visibleTo($request->user())->whereKey($student->id)->exists(), 403, 'This student is not in a section you teach.');
    }

    private function formData(Student $student): array
    {
        return [
            'student' => $student,
            'programs' => Program::orderBy('name')->get(),
            'sections' => Section::with(['program', 'academicSession'])->orderBy('program_id')->orderBy('year')->get(),
            'sessions' => AcademicSession::orderByDesc('starts_on')->pluck('name', 'id'),
            'statuses' => self::STATUSES,
            'bloodGroups' => array_combine(self::BLOOD_GROUPS, self::BLOOD_GROUPS),
        ];
    }

    private function validated(Request $request, ?Student $student = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'min:3', 'max:120', "regex:/^[\\pL .'-]+$/u"],
            'father_name' => ['required', 'string', 'min:3', 'max:120', "regex:/^[\\pL .'-]+$/u"],
            'roll_no' => ['required', 'string', 'max:32', 'regex:/^[A-Z0-9-]+$/', Rule::unique('students', 'roll_no')->ignore($student?->id)],
            'registration_no' => ['required', 'string', 'max:40', Rule::unique('students', 'registration_no')->ignore($student?->id)],
            'cnic' => ['required', 'regex:/^\d{5}-\d{7}-\d$/', Rule::unique('students', 'cnic')->ignore($student?->id)],
            'father_cnic' => ['required', 'regex:/^\d{5}-\d{7}-\d$/', 'different:cnic'],
            'date_of_birth' => ['required', 'date', 'before:-13 years', 'after:-40 years'],
            'gender' => ['required', Rule::in(['male', 'female'])],
            'blood_group' => ['required', Rule::in(self::BLOOD_GROUPS)],
            'phone' => ['required', 'regex:/^03\d{2}-\d{7}$/'],
            'guardian_phone' => ['required', 'regex:/^03\d{2}-\d{7}$/'],
            'email' => ['required', 'email:rfc', 'max:120'],
            'address' => ['required', 'string', 'min:8', 'max:255'],
            'city' => ['required', 'string', 'max:64'],
            'domicile' => ['required', 'string', 'max:64'],
            'program_id' => ['required', 'exists:programs,id'],
            'section_id' => ['required', Rule::exists('sections', 'id')->where('program_id', $request->integer('program_id'))],
            'academic_session_id' => ['required', 'exists:academic_sessions,id'],
            'status' => ['required', Rule::in(array_keys(self::STATUSES))],
        ], [
            'cnic.regex' => 'Use the format 37405-1234567-1.',
            'father_cnic.regex' => 'Use the format 37405-1234567-1.',
            'father_cnic.different' => "Father's CNIC cannot be the same as the student's.",
            'phone.regex' => 'Use the format 0300-1234567.',
            'guardian_phone.regex' => 'Use the format 0300-1234567.',
            'section_id.exists' => 'This section does not belong to the selected program.',
            'roll_no.regex' => 'Use capital letters, digits and dashes only (e.g. FSC-PM-26-041).',
            'date_of_birth.before' => 'Student must be at least 13 years old.',
            'name.regex' => 'Name may contain letters, spaces, dots and dashes only.',
            'father_name.regex' => 'Name may contain letters, spaces, dots and dashes only.',
        ]);

        $section = Section::find($data['section_id']);
        if ($section && $section->academic_session_id !== (int) $data['academic_session_id']) {
            $data['academic_session_id'] = $section->academic_session_id;
        }

        return $data;
    }
}
