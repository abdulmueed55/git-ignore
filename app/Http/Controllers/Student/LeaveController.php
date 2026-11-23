<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\LeaveRequest;
use App\Models\User;
use App\Notifications\StaffAlert;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** A student asks for leave; the principal's office approves or rejects it in Attendance → Leave requests. */
class LeaveController extends Controller
{
    public function index(Request $request): View
    {
        $s = $request->user('student');

        return view('student.leave', [
            'student' => $s,
            'leaves' => LeaveRequest::with('decider')->where('student_id', $s->id)->latest('from_date')->limit(20)->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $s = $request->user('student');
        $data = $request->validate([
            'from_date' => ['required', 'date', 'after_or_equal:-7 days', 'before_or_equal:+30 days'],
            'to_date' => ['required', 'date', 'after_or_equal:from_date'],
            'reason' => ['required', 'string', 'min:10', 'max:255'],
        ], [
            'from_date.after_or_equal' => 'Leave can be asked for at most 7 days back.',
            'from_date.before_or_equal' => 'Leave can be asked for at most 30 days ahead.',
            'to_date.after_or_equal' => 'The leave must end on or after the day it starts.',
            'reason.min' => 'Write the reason in a few words (e.g. fever, family wedding).',
        ]);
        if (Carbon::parse($data['from_date'])->diffInDays(Carbon::parse($data['to_date'])) + 1 > 15) {
            return back()->withInput()->withErrors(['to_date' => 'One request can be for at most 15 days. For longer leave, visit the college office.']);
        }
        if (LeaveRequest::where('student_id', $s->id)->where('status', 'pending')->count() >= 3) {
            return back()->withInput()->withErrors(['from_date' => 'You already have 3 requests waiting for a decision.']);
        }

        $leave = LeaveRequest::create($data + ['student_id' => $s->id, 'status' => 'pending', 'source' => 'student']);
        ActivityLog::record('student.leave_requested', "Student {$s->name} ({$s->roll_no}) asked for leave ({$leave->days()} day(s))", $leave);

        // Tell the principal's office in the bell.
        User::whereIn('role', ['principal', 'admin'])->where('is_active', true)->get()->each->notify(new StaffAlert(
            "leave:{$leave->id}", 'leave_request', "Leave request: {$s->name}",
            "{$s->roll_no} · ".$leave->from_date->format('j M').($leave->days() > 1 ? '–'.$leave->to_date->format('j M') : '')." · {$leave->reason}",
            route('attendance.leaves.index'),
        ));

        return redirect()->route('student.leave')->with('success', 'Leave request sent. You will see the decision here.');
    }
}
