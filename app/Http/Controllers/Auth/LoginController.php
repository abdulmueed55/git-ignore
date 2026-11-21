<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class LoginController extends Controller
{
    /** bcrypt (cost 12) of a random string nobody knows — used only to equalise timing. */
    private const DUMMY_HASH = '$2y$12$CXSe/vImF8vq08kbazi5LeyNFWLR0d5qcqWx/7ccMKuLU2UHeT02e';

    public function show(): View
    {
        return view('auth.login');
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $email = Str::lower(trim($credentials['email']));
        $key = $email.'|'.$request->ip();
        // Also cap guesses per account across all IPs (spread-out / rotating-IP brute force).
        $accountKey = 'login-account:'.sha1($email);
        foreach ([[$key, 5], [$accountKey, 10]] as [$k, $max]) {
            if (RateLimiter::tooManyAttempts($k, $max)) {
                throw ValidationException::withMessages([
                    'email' => 'Too many sign-in attempts. Try again in '.max(1, (int) ceil(RateLimiter::availableIn($k) / 60)).' minute(s).',
                ]);
            }
        }

        if (! Auth::attempt([...$credentials, 'is_active' => true], $request->boolean('remember'))) {
            // Unknown or inactive accounts skip the password check inside attempt(); do one against a
            // dummy hash so response time does not reveal which staff emails exist.
            $user = User::where('email', $email)->first();
            if (! $user || ! $user->is_active) {
                Hash::check($credentials['password'], self::DUMMY_HASH);
            }
            RateLimiter::hit($key, 60);
            RateLimiter::hit($accountKey, 900);
            ActivityLog::record('auth.failed', 'Failed sign-in for '.Str::limit($email, 60));
            throw ValidationException::withMessages(['email' => 'These credentials do not match an active account.']);
        }

        RateLimiter::clear($key);
        RateLimiter::clear($accountKey);
        $request->session()->regenerate();
        $request->user()->forceFill(['last_login_at' => now()])->save();
        ActivityLog::record('auth.login', 'Signed in');

        $home = $request->user()->role === 'editor' ? route('studio.dashboard') : route('dashboard');

        return redirect()->intended($home);
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
