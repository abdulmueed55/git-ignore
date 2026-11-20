<?php

namespace App\Http\Controllers\Attendance;

use App\Models\Program;
use App\Models\Section;
use App\Services\Attendance\Scope;
use Carbon\Carbon;
use Illuminate\Http\Request;

/** Shared filter parsing (program / section / date range) scoped to the user. */
trait Filters
{
    protected function filters(Request $request, int $defaultDays = 30): array
    {
        $user = $request->user();
        $to = $this->parseDate($request->input('to')) ?? today();
        $from = $this->parseDate($request->input('from')) ?? $to->copy()->subDays($defaultDays - 1);
        if ($from->gt($to)) {
            [$from, $to] = [$to, $from];
        }

        return [
            'course_ids' => Scope::courseIds($user),
            'program_id' => $request->integer('program_id') ?: null,
            'section_id' => $request->integer('section_id') ?: null,
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
        ];
    }

    protected function filterOptions(Request $request): array
    {
        $sectionIds = Scope::sectionIds($request->user());
        $sections = Section::with('program')
            ->when($sectionIds !== null, fn ($q) => $q->whereIn('id', $sectionIds ?: [0]))
            ->get()->sortBy(fn ($s) => $s->program->code.$s->name);

        return [
            'programs' => Program::whereIn('id', $sections->pluck('program_id')->unique())->orderBy('code')->pluck('code', 'id'),
            'sections' => $sections->mapWithKeys(fn ($s) => [$s->id => $s->label()]),
        ];
    }

    private function parseDate($v): ?Carbon
    {
        try {
            return $v ? Carbon::parse($v)->startOfDay() : null;
        } catch (\Throwable) {
            return null;
        }
    }
}
