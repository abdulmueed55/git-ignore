<?php

namespace App\Http\Controllers\Attendance;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\AttendanceRecord;
use App\Models\LeaveRequest;
use App\Models\Lecture;
use App\Models\Student;
use App\Services\Attendance\Scope;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class LeaveController extends Controller
{
    public function index(Request $request): View
    {
        $sectionIds = Scope::sectionIds($request->user());
        $status = in_array($request->input('status'), ['pending', 'approved', 'rejected', 'all'], true) ? $request->input('status') : 'pending';
        $scoped = fn ($q) => $q->when($sectionIds !== null, fn ($q) => $q->whereHas('student', fn ($s) => $s->whereIn('section_id', $sectionIds ?: [0])));

        $leaves = LeaveRequest::with(['student.section.program', 'decider'])
            ->tap($scoped)
            ->when($status !== 'all', fn ($q) => $q->where('status', $status))
            ->when($request->filled('q'), fn ($q) => $q->whereHas('student', fn ($s) => $s->where('name', 'like', '%'.$request->input('q').'%')->orWhere('roll_no', 'like', '%'.$request->input('q').'%')))
            ->orderByRaw("CASE status WHEN 'pending' THEN 0 ELSE 1 END")->latest('from_date')
            ->paginate(15)->withQueryString();

        $counts = LeaveRequest::tap($scoped)->selectRaw('status, count(*) n')->groupBy('status')->pluck('n', 'status');

        $students = Student::with('section.program')->where('status', 'active')
            ->when($sectionIds !== null, fn ($q) => $q->whereIn('section_id', $sectionIds ?: [0]))
            ->orderBy('roll_no')->get()
            ->groupBy(fn ($s) => $s->section?->label() ?? 'Unassigned');

        return view('attendance.leaves', compact('leaves', 'counts', 'status', 'students'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'student_id' => ['required', 'integer', Rule::exists('students', 'id')],
            'from_date' => ['required', 'date'],
            'to_date' => ['required', 'date', 'after_or_equal:from_date'],
            'reason' => ['required', 'string', 'min:5', 'max:255'],
        ], ['to_date.after_or_equal' => 'The leave must end on or after the day it starts.']);

        $student = Student::findOrFail($data['student_id']);
        abort_unless(Scope::canViewStudent($request->user(), $student), 403);

        $leave = LeaveRequest::create($data + ['status' => 'pending']);
        ActivityLog::record('attendance.leave_filed', "Filed leave for {$student->name} ({$leave->days()} day(s))", $leave);

        return redirect()->route('attendance.leaves.index')->with('success', "Leave request filed for {$student->name}.");
    }

    public function approve(Request $request, LeaveRequest $leave): RedirectResponse
    {
        $this->authorizeLeave($request, $leave);

        $converted = DB::transaction(function () use ($leave, $request) {
            $leave->update(['status' => 'approved', 'decided_by' => $request->user()->id]);
            $lectureIds = Lecture::whereBetween('held_on', [$leave->from_date->toDateString(), $leave->to_date->toDateString()])->pluck('id');
            $records = AttendanceRecord::where('student_id', $leave->student_id)->whereIn('lecture_id', $lectureIds)->where('status', 'A');
            $affected = (clone $records)->pluck('lecture_id');
            $records->update(['status' => 'E', 'remarks' => 'Approved leave']);
            Lecture::whereIn('id', $affected)->get()->each->refreshCounts();

            return $affected->count();
        });

        ActivityLog::record('attendance.leave_approved', "Approved leave for {$leave->student->name}", $leave);

        return back()->with('success', "Leave approved for {$leave->student->name}.".($converted ? " {$converted} absence(s) converted to leave." : ''));
    }

    public function reject(Request $request, LeaveRequest $leave): RedirectResponse
    {
        $this->authorizeLeave($request, $leave);
        $leave->update(['status' => 'rejected', 'decided_by' => $request->user()->id]);
        ActivityLog::record('attendance.leave_rejected', "Rejected leave for {$leave->student->name}", $leave);

        return back()->with('success', "Leave rejected for {$leave->student->name}.");
    }

    private function authorizeLeave(Request $request, LeaveRequest $leave): void
    {
        abort_unless(Scope::canViewStudent($request->user(), $leave->student), 403);
        abort_if($leave->status !== 'pending', 422, 'This leave request has already been decided.');
    }
}
