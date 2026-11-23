<?php

namespace App\Http\Controllers\Apply;

use App\Http\Controllers\Controller;
use App\Models\AdmissionApplication;
use App\Models\MeritListEntry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\View\View;

class StatusController extends Controller
{
    public function show(Request $request): View
    {
        $app = null;
        $tracking = $request->query('tracking_no');
        if ($tracking && in_array($tracking, $request->session()->get('apply_access', []), true)) {
            $app = AdmissionApplication::with(['program', 'cycle', 'documents', 'checks', 'student.section.program'])->where('tracking_no', $tracking)->first();
        }

        $entry = $app ? MeritListEntry::with('meritList.program')
            ->where('admission_application_id', $app->id)
            ->whereHas('meritList', fn ($q) => $q->where('status', 'published'))
            ->latest()->first() : null;

        return view('apply.status', compact('app', 'entry', 'tracking'));
    }

    public function lookup(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'tracking_no' => ['required', 'string', 'max:24'],
            'pin' => ['required', 'digits:6'],
        ], ['pin.digits' => 'The PIN is the 6-digit number shown when you started your application.']);

        $tracking = strtoupper(trim($data['tracking_no']));

        // Lock a tracking number after repeated wrong PINs, whatever IP the guesses come from.
        $key = 'apply-pin:'.sha1($tracking);
        $daily = 'apply-pin-day:'.sha1($tracking);
        if (RateLimiter::tooManyAttempts($key, 5) || RateLimiter::tooManyAttempts($daily, 20)) {
            $wait = max(RateLimiter::availableIn($key), RateLimiter::tooManyAttempts($daily, 20) ? RateLimiter::availableIn($daily) : 0);

            return back()->withInput($request->only('tracking_no'))
                ->withErrors(['pin' => 'Too many wrong PINs for this tracking number. Try again in '.max(1, (int) ceil($wait / 60)).' minutes, or contact the admissions office.']);
        }

        $app = AdmissionApplication::where('tracking_no', $tracking)->first();
        if (! $app || ! $app->pinMatches($data['pin'])) {
            RateLimiter::hit($key, 900);
            RateLimiter::hit($daily, 86400);

            return back()->withInput($request->only('tracking_no'))->withErrors(['pin' => 'Tracking number and PIN do not match.']);
        }
        RateLimiter::clear($key);
        ApplyController::grant($request, $app, $data['pin']);

        return $app->status === 'draft'
            ? redirect()->route('apply.documents', $app->tracking_no)->with('success', 'Welcome back — continue where you left off.')
            : redirect()->route('apply.status', ['tracking_no' => $app->tracking_no]);
    }
}
