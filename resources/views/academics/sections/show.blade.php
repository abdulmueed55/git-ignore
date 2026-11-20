<x-layouts.app :title="$section->label()">
    <x-ui.page-header :eyebrow="$section->program->name.' · Session '.$section->academicSession?->name" :title="$section->name"
        :description="'Room '.$section->room.' · Incharge: '.($section->incharge?->name ?? 'not assigned')">
        <x-slot:actions>
            <a href="{{ route('academics.timetable', ['section' => $section->id]) }}" class="btn btn-ghost"><x-icon name="clock" :size="15" /> Timetable</a>
            <a href="{{ route('academics.sections.edit', $section) }}" class="btn btn-primary"><x-icon name="edit" :size="15" /> Edit</a>
        </x-slot:actions>
    </x-ui.page-header>

    @php
        $active = $students->where('status', 'active')->count();
        $periods = $section->courses->sum(fn ($c) => $c->slots->count());
    @endphp
    <div class="mb-6 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <div class="panel flex items-center gap-4 p-5">
            <x-ui.ring :value="$section->capacity ? min(100, $active / $section->capacity * 100) : 0" :size="72" :stroke="7" />
            <div><div class="eyebrow">Strength</div><div class="numeral mt-1 text-[28px]">{{ $active }}<span class="text-[14px] text-muted"> / {{ $section->capacity }}</span></div></div>
        </div>
        <x-ui.stat label="Male / female" :value="$students->where('gender', 'male')->count().' / '.$students->where('gender', 'female')->count()" icon="users" />
        <x-ui.stat label="Courses" :value="$section->courses->count()" icon="book" />
        <x-ui.stat label="Periods / week" :value="$periods" icon="clock" />
    </div>

    <div class="grid gap-6 xl:grid-cols-[1fr_400px]">
        <section class="panel overflow-hidden">
            <div class="panel-head">
                <div class="panel-title">Class roster</div>
                <a href="{{ route('students.index', ['section' => $section->id]) }}" class="btn btn-ghost btn-sm">Open in students <x-icon name="arrow-right" :size="14" /></a>
            </div>
            @if ($students->count())
                <div class="max-h-[640px] overflow-y-auto scrollbar-thin">
                    <table class="table">
                        <thead><tr><th class="w-10">#</th><th>Student</th><th>Roll no</th><th class="hidden md:table-cell">Guardian</th><th>Status</th></tr></thead>
                        <tbody>
                        @foreach ($students as $i => $s)
                            <tr>
                                <td class="mono text-[11.5px] text-muted">{{ str_pad($i + 1, 2, '0', STR_PAD_LEFT) }}</td>
                                <td><a href="{{ route('students.show', $s) }}" class="flex items-center gap-2.5"><x-ui.avatar :name="$s->name" :size="28" /><span class="font-medium text-ink hover:text-brand">{{ $s->name }}</span></a></td>
                                <td class="mono text-[12px]">{{ $s->roll_no }}</td>
                                <td class="mono hidden text-[12px] md:table-cell">{{ $s->guardian_phone }}</td>
                                <td><x-ui.status :value="$s->status" /></td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <x-ui.empty icon="users" title="No students in this section yet" description="Admitted applicants are placed here automatically; you can also enrol a student manually.">
                    <a href="{{ route('students.create') }}" class="btn btn-primary"><x-icon name="plus" :size="15" /> Enrol student</a>
                </x-ui.empty>
            @endif
        </section>

        <section class="panel h-max">
            <div class="panel-head"><div class="panel-title">Courses & teachers</div></div>
            @forelse ($section->courses as $c)
                <div class="flex items-center gap-3 border-b border-line px-5 py-3 last:border-0">
                    <x-academics.dept-swatch :code="$c->subject->department?->code" />
                    <div class="min-w-0 flex-1">
                        <div class="truncate text-[13.5px] font-medium text-ink">{{ $c->subject->name }}</div>
                        <div class="truncate text-[12px] text-muted">{{ $c->teacher?->name ?? 'Teacher not assigned' }}</div>
                    </div>
                    <span class="mono text-[11px] text-muted">{{ $c->slots->count() }}×/wk</span>
                </div>
            @empty
                <x-ui.empty icon="book" title="No courses yet" description="Add subjects to this section from the timetable planner.">
                    <a href="{{ route('academics.timetable', ['section' => $section->id]) }}" class="btn btn-primary">Open planner</a>
                </x-ui.empty>
            @endforelse
        </section>
    </div>
</x-layouts.app>
