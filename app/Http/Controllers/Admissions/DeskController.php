<?php

namespace App\Http\Controllers\Admissions;

use App\Http\Controllers\Controller;
use App\Models\AdmissionApplication;
use App\Models\AdmissionCycle;
use App\Models\ApplicationDocument;
use Carbon\CarbonPeriod;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DeskController extends Controller
{
    public function __invoke(Request $request): View
    {
        $cycles = AdmissionCycle::orderByDesc('opens_at')->get();
        $cycle = $cycles->firstWhere('id', (int) $request->query('cycle')) ?? AdmissionCycle::active() ?? $cycles->first();

        $apps = $cycle ? AdmissionApplication::with('program')->where('admission_cycle_id', $cycle->id)->get() : collect();
        $submitted = $apps->where('status', '!=', 'draft');

        $funnel = [
            ['Started', $apps->count(), 'muted'],
            ['Submitted', $submitted->count(), 'brass'],
            ['Verified', $submitted->whereIn('status', ['verified', 'selected', 'admitted', 'waitlisted'])->count(), 'info'],
            ['Selected', $submitted->whereIn('status', ['selected', 'admitted'])->count(), 'brand'],
            ['Admitted', $submitted->where('status', 'admitted')->count(), 'ok'],
        ];

        $programs = $cycle ? $cycle->programs() : collect();
        $demand = $programs->map(fn ($p) => [
            'program' => $p,
            'applied' => $submitted->where('program_id', $p->id)->count(),
            'selected' => $submitted->where('program_id', $p->id)->whereIn('status', ['selected', 'admitted'])->count(),
            'admitted' => $submitted->where('program_id', $p->id)->where('status', 'admitted')->count(),
            'top' => $submitted->where('program_id', $p->id)->max('merit_score'),
        ])->sortByDesc('applied')->values();

        $docs = $cycle ? ApplicationDocument::whereHas('application', fn ($q) => $q->where('admission_cycle_id', $cycle->id))->get(['extraction_status', 'confidence', 'type']) : collect();
        $read = $docs->where('extraction_status', 'done');
        $checked = $submitted->whereNotNull('verification_score');
        $health = [
            'read' => $read->count(),
            'total' => $docs->where('type', '!=', 'photo')->count(),
            'confidence' => $read->avg('confidence') ? round($read->avg('confidence')) : null,
            'auto_cleared' => $checked->count() ? round($checked->where('verification_status', 'clear')->count() / $checked->count() * 100) : null,
            'mismatch' => $checked->where('verification_status', 'mismatch')->count(),
            'review' => $checked->where('verification_status', 'review')->count(),
            'failed' => $docs->where('extraction_status', 'failed')->count(),
        ];

        $from = $cycle ? $cycle->opens_at->copy()->startOfDay() : now()->subDays(20);
        $to = $cycle ? min(now(), $cycle->closes_at)->copy()->startOfDay() : now();
        if ($to->lt($from)) {
            $to = $from->copy();
        }
        $byDay = $submitted->groupBy(fn ($a) => ($a->submitted_at ?? $a->created_at)->toDateString());
        $days = collect(CarbonPeriod::create($from, $to))->take(-45);
        $daily = ['labels' => $days->map->format('j M')->values(), 'data' => $days->map(fn ($d) => $byDay->get($d->toDateString())?->count() ?? 0)->values()];

        $flagged = $submitted->whereIn('verification_status', ['mismatch', 'review'])->whereNotIn('status', ['admitted', 'rejected'])
            ->sortBy('verification_score')->take(6)->each->load('checks');

        $quotas = $submitted->groupBy('quota')->map->count()->sortDesc();

        return view('admissions.desk', compact('cycle', 'cycles', 'funnel', 'demand', 'health', 'daily', 'flagged', 'quotas', 'submitted'));
    }
}
