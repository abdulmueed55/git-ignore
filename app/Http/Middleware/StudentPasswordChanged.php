<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** A student signing in with the admission PIN (or a reset password) must choose a new password first. */
class StudentPasswordChanged
{
    public function handle(Request $request, Closure $next): Response
    {
        $student = $request->user('student');
        if ($student && $student->status !== 'active') {
            auth('student')->logout();
            $request->session()->invalidate();

            return redirect()->route('student.login')->withErrors(['roll_no' => 'This student account is not active. Please contact the college office.']);
        }
        if ($student?->must_change_password && ! $request->routeIs('student.password.*')) {
            return redirect()->route('student.password.edit');
        }

        return $next($request);
    }
}
