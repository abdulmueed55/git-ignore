<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class PasswordController extends Controller
{
    public function edit(Request $request): View
    {
        return view('student.password', ['student' => $request->user('student')]);
    }

    public function update(Request $request): RedirectResponse
    {
        $student = $request->user('student');
        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'confirmed', 'different:current_password', Password::min(8)->letters()->numbers()],
        ], [
            'password.different' => 'Choose a new password, different from the current one.',
        ]);

        if (! Hash::check($data['current_password'], $student->password)) {
            return back()->withErrors(['current_password' => $student->must_change_password
                ? 'This is not your admission PIN (or the temporary password from the college office).'
                : 'Your current password is not correct.']);
        }
        if (str_contains(strtolower($data['password']), strtolower($student->roll_no))) {
            return back()->withErrors(['password' => 'Do not use your roll number in the password.']);
        }

        $student->forceFill(['password' => $data['password'], 'must_change_password' => false])->save();
        $request->session()->regenerate();
        ActivityLog::record('student.password', "Student {$student->name} ({$student->roll_no}) set a new portal password", $student);

        return redirect()->route('student.home')->with('success', 'Password saved. Use it with your roll number from now on.');
    }
}
