<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Student;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AuthController extends Controller
{
    /** bcrypt (cost 12) of a random string — equalises timing for unknown roll numbers. */
    private const DUMMY_HASH = '$2y$12$CXSe/vImF8vq08kbazi5LeyNFWLR0d5qcqWx/7ccMKuLU2UHeT02e';

    public function show(): View
    {
        return view('student.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'roll_no' => ['required', 'string', 'max:32'],
            'password' => ['required', 'string', 'max:128'],
        ], ['roll_no.required' => 'Enter your roll number, e.g. FSC-PM-26-001.']);

        $roll = strtoupper(trim($data['roll_no']));
        $keys = [[$roll.'|'.$request->ip(), 5, 60], ['student-account:'.sha1($roll), 10, 900]];
        foreach ($keys as [$key, $max]) {
            if (RateLimiter::tooManyAttempts($key, $max)) {
                throw ValidationException::withMessages(['roll_no' => 'Too many sign-in attempts. Try again in '.max(1, (int) ceil(RateLimiter::availableIn($key) / 60)).' minute(s).']);
            }
        }

        if (! Auth::guard('student')->attempt(['roll_no' => $roll, 'password' => $data['password'], 'status' => 'active'], $request->boolean('remember'))) {
            $student = Student::where('roll_no', $roll)->first();
            if (! $student || $student->status !== 'active' || ! $student->hasPortal()) {
                Hash::check($data['password'], self::DUMMY_HASH);
            }
            foreach ($keys as [$key, , $decay]) {
                RateLimiter::hit($key, $decay);
            }
            throw ValidationException::withMessages(['roll_no' => 'Roll number and password do not match. New students: use the PIN from your admission application.']);
        }

        foreach ($keys as [$key]) {
            RateLimiter::clear($key);
        }
        $request->session()->regenerate();
        $student = $request->user('student');
        $student->forceFill(['portal_last_login_at' => now()])->saveQuietly();
        ActivityLog::record('student.login', "Student {$student->name} ({$student->roll_no}) signed in to the student portal", $student);

        return redirect()->intended(route('student.home'));
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::guard('student')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('student.login')->with('success', 'You have signed out.');
    }
}
