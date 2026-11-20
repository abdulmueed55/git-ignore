<?php

namespace App\Http\Controllers\Attendance;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\GuardianAlert;
use App\Services\Attendance\AlertService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AlertController extends Controller
{
    public function index(Request $request): View
    {
        $status = in_array($request->input('status'), ['queued', 'sent', 'failed', 'all'], true) ? $request->input('status') : 'queued';
        $kind = array_key_exists((string) $request->input('kind'), AlertService::KINDS) ? $request->input('kind') : null;

        $alerts = GuardianAlert::with('student.section.program')
            ->when($status !== 'all', fn ($q) => $q->where('status', $status))
            ->when($kind, fn ($q) => $q->where('kind', $kind))
            ->latest()->paginate(20)->withQueryString();

        $counts = GuardianAlert::selectRaw('status, count(*) n')->groupBy('status')->pluck('n', 'status');
        $byKind = GuardianAlert::where('created_at', '>=', now()->subDays(30))->selectRaw('kind, count(*) n')->groupBy('kind')->pluck('n', 'kind');
        $sentToday = GuardianAlert::where('status', 'sent')->where('sent_at', '>=', today())->count();

        return view('attendance.alerts', [
            'alerts' => $alerts, 'counts' => $counts, 'byKind' => $byKind, 'status' => $status, 'kind' => $kind, 'sentToday' => $sentToday,
            'driver' => config('attendance.sms.driver'), 'kinds' => AlertService::KINDS,
        ]);
    }

    public function send(Request $request, AlertService $service): RedirectResponse
    {
        $data = $request->validate(['ids' => ['nullable', 'array'], 'ids.*' => ['integer']]);
        $alerts = GuardianAlert::where('status', 'queued')
            ->when(! empty($data['ids']), fn ($q) => $q->whereIn('id', $data['ids']))
            ->get();
        if ($alerts->isEmpty()) {
            return back()->with('error', 'There are no queued alerts to send.');
        }
        [$sent, $failed] = $service->sendQueued($alerts);
        ActivityLog::record('attendance.alerts_sent', "Sent {$sent} guardian SMS alerts ({$failed} failed)");

        return back()->with($failed ? 'error' : 'success', "{$sent} alert(s) sent".($failed ? ", {$failed} failed — check the numbers." : '.'));
    }
}
