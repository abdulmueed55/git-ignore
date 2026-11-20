<?php

namespace App\Http\Controllers\Admissions;

use App\Http\Controllers\Controller;
use App\Models\AdmissionCycle;
use App\Models\MeritList;
use App\Models\Program;
use App\Services\Admissions\MeritListService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MeritListController extends Controller
{
    public function __construct(private readonly MeritListService $service) {}

    public function index(Request $request): View
    {
        $cycles = AdmissionCycle::orderByDesc('opens_at')->get();
        $cycle = $cycles->firstWhere('id', (int) $request->query('cycle')) ?? AdmissionCycle::active() ?? $cycles->first();
        $lists = $cycle ? MeritList::with('program')->withCount('entries')->where('admission_cycle_id', $cycle->id)->orderBy('program_id')->orderBy('list_no')->get() : collect();

        $programs = $cycle ? $cycle->programs()->map(fn (Program $p) => [
            'program' => $p,
            'candidates' => $this->service->candidates($cycle, $p)->count(),
            'seats_left' => $this->service->seatsLeft($cycle, $p),
            'lists' => $lists->where('program_id', $p->id)->values(),
        ]) : collect();

        return view('admissions.merit.index', compact('cycle', 'cycles', 'programs', 'lists'));
    }

    public function generate(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'admission_cycle_id' => ['required', 'exists:admission_cycles,id'],
            'program_id' => ['required', 'exists:programs,id'],
            'seats' => ['nullable', 'integer', 'min:1', 'max:500'],
        ]);
        $cycle = AdmissionCycle::findOrFail($data['admission_cycle_id']);
        $program = Program::findOrFail($data['program_id']);

        if ($this->service->seatsLeft($cycle, $program) === 0) {
            return back()->with('error', "All {$program->seats} seats of {$program->code} are already filled.");
        }
        if ($this->service->candidates($cycle, $program)->isEmpty()) {
            return back()->with('error', "No verified applicants above {$program->min_percentage}% for {$program->code} yet.");
        }
        $list = $this->service->generate($cycle, $program, $data['seats'] ?? null);

        return redirect()->route('admissions.merit.show', $list)->with('success', "Draft merit list #{$list->list_no} prepared — review, then publish.");
    }

    public function show(MeritList $meritList): View
    {
        $meritList->load(['program', 'cycle', 'entries.application']);

        return view('admissions.merit.show', ['list' => $meritList]);
    }

    public function print(MeritList $meritList): View
    {
        $meritList->load(['program', 'cycle.academicSession', 'entries.application']);

        return view('admissions.merit.print', ['list' => $meritList]);
    }

    public function publish(Request $request, MeritList $meritList): RedirectResponse
    {
        abort_unless($meritList->status === 'draft', 422, 'This list is already published.');
        $data = $request->validate(['fee_deadline' => ['required', 'date', 'after_or_equal:today']]);
        $this->service->publish($meritList, $data['fee_deadline']);

        return redirect()->route('admissions.merit.show', $meritList)->with('success', 'Merit list published — '.$meritList->entries()->count().' applicants selected.');
    }

    public function destroy(MeritList $meritList): RedirectResponse
    {
        abort_unless($meritList->status === 'draft', 422, 'Published lists cannot be deleted.');
        $cycle = $meritList->admission_cycle_id;
        $meritList->delete();

        return redirect()->route('admissions.merit.index', ['cycle' => $cycle])->with('success', 'Draft discarded.');
    }
}
