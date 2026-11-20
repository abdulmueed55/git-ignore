<?php

namespace App\Services\Admissions;

use App\Models\ActivityLog;
use App\Models\AdmissionApplication;
use App\Models\AdmissionCycle;
use App\Models\MeritList;
use App\Models\Program;
use Illuminate\Support\Facades\DB;

class MeritListService
{
    public function __construct(private readonly MeritCalculator $calc = new MeritCalculator) {}

    /** Seats still open for a program in this cycle. */
    public function seatsLeft(AdmissionCycle $cycle, Program $program): int
    {
        $taken = AdmissionApplication::where('admission_cycle_id', $cycle->id)
            ->where('program_id', $program->id)
            ->whereIn('status', ['selected', 'admitted'])->count();

        return max(0, $program->seats - $taken);
    }

    /** Verified applicants above the program minimum, best first. */
    public function candidates(AdmissionCycle $cycle, Program $program)
    {
        return AdmissionApplication::with('program')
            ->where('admission_cycle_id', $cycle->id)
            ->where('program_id', $program->id)
            ->whereIn('status', ['verified', 'waitlisted'])
            ->get()
            ->each(fn ($a) => $a->merit_score === null ? $this->calc->apply($a) : null)
            ->filter(fn ($a) => $a->merit_score !== null && ($this->calc->eligibilityPercentage($a) ?? 0) >= $program->min_percentage)
            ->sortByDesc('merit_score')
            ->values();
    }

    public function generate(AdmissionCycle $cycle, Program $program, ?int $seats = null): MeritList
    {
        return DB::transaction(function () use ($cycle, $program, $seats) {
            // Replace an unpublished draft instead of piling up drafts.
            MeritList::where('admission_cycle_id', $cycle->id)->where('program_id', $program->id)->where('status', 'draft')->get()->each->delete();

            $seats = min($seats ?? PHP_INT_MAX, $this->seatsLeft($cycle, $program));
            $chosen = $this->candidates($cycle, $program)->take($seats)->values();

            $list = MeritList::create([
                'admission_cycle_id' => $cycle->id,
                'program_id' => $program->id,
                'list_no' => (int) MeritList::where('admission_cycle_id', $cycle->id)->where('program_id', $program->id)->max('list_no') + 1,
                'seats_offered' => $chosen->count(),
                'closing_merit' => $chosen->last()?->merit_score,
                'status' => 'draft',
            ]);
            foreach ($chosen as $i => $a) {
                $list->entries()->create(['admission_application_id' => $a->id, 'position' => $i + 1, 'merit_score' => $a->merit_score]);
            }
            ActivityLog::record('admissions.merit_generated', "Generated merit list #{$list->list_no} for {$program->code} ({$chosen->count()} seats)", $list);

            return $list;
        });
    }

    public function publish(MeritList $list, ?string $feeDeadline = null): MeritList
    {
        return DB::transaction(function () use ($list, $feeDeadline) {
            $ids = $list->entries()->pluck('admission_application_id');
            AdmissionApplication::whereIn('id', $ids)->update(['status' => 'selected', 'reviewed_at' => now()]);

            // Everyone else still eligible waits for the next list.
            $this->candidates($list->cycle, $list->program)
                ->whereNotIn('id', $ids)
                ->each(fn ($a) => $a->update(['status' => 'waitlisted']));

            $list->update(['status' => 'published', 'published_at' => now(), 'fee_deadline' => $feeDeadline ?? now()->addDays(7)->toDateString()]);
            ActivityLog::record('admissions.merit_published', "Published merit list #{$list->list_no} for {$list->program->code}", $list);

            return $list;
        });
    }
}
