<?php

namespace App\Http\Controllers\Apply;

use App\Http\Controllers\Controller;
use App\Models\AdmissionApplication;
use App\Models\MeritListEntry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StatusController extends Controller
{
    public function show(Request $request): View
    {
        $app = null;
        $tracking = $request->query('tracking_no');
        if ($tracking && in_array($tracking, $request->session()->get('apply_access', []), true)) {
            $app = AdmissionApplication::with(['program', 'cycle', 'documents', 'checks'])->where('tracking_no', $tracking)->first();
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

        $app = AdmissionApplication::where('tracking_no', strtoupper(trim($data['tracking_no'])))->first();
        if (! $app || ! hash_equals($app->access_pin, $data['pin'])) {
            return back()->withInput($request->only('tracking_no'))->withErrors(['pin' => 'Tracking number and PIN do not match.']);
        }
        ApplyController::grant($request, $app);

        return $app->status === 'draft'
            ? redirect()->route('apply.documents', $app->tracking_no)->with('success', 'Welcome back — continue where you left off.')
            : redirect()->route('apply.status', ['tracking_no' => $app->tracking_no]);
    }
}
