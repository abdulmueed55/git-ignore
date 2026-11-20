<x-layouts.app title="Sections">
    <x-ui.page-header eyebrow="Academics" title="Sections" description="Class sections by program and session — strength, incharge and teaching load.">
        <x-slot:actions><a href="{{ route('academics.sections.create') }}" class="btn btn-primary"><x-icon name="plus" :size="16" /> New section</a></x-slot:actions>
    </x-ui.page-header>

    <form method="GET" class="mb-6 flex flex-wrap gap-2" x-data>
        <select name="program" class="input !w-auto min-w-[220px]" @change="$el.form.submit()">
            <option value="">All programs</option>
            @foreach ($programs as $id => $n)<option value="{{ $id }}" @selected(request('program') == $id)>{{ $n }}</option>@endforeach
        </select>
        <select name="session" class="input !w-auto min-w-[160px]" @change="$el.form.submit()">
            <option value="">All sessions</option>
            @foreach ($sessions as $id => $n)<option value="{{ $id }}" @selected(request('session') == $id)>{{ $n }}</option>@endforeach
        </select>
        <span class="ml-auto self-center text-[12.5px] text-muted"><span class="mono text-ink">{{ $count }}</span> sections</span>
    </form>

    @forelse ($groups as $program => $sections)
        <div class="mb-3 mt-8 flex items-center gap-3 first:mt-0">
            <span class="display text-[17px] text-ink">{{ $program }}</span><span class="h-px flex-1 bg-line"></span>
        </div>
        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            @foreach ($sections as $s)
                @php $pct = $s->capacity ? min(100, round($s->students_count / $s->capacity * 100)) : 0; @endphp
                <a href="{{ route('academics.sections.show', $s) }}" class="panel group p-5 transition hover:border-line-strong">
                    <div class="flex items-start justify-between">
                        <div>
                            <div class="mono text-[11px] text-brass">{{ $s->program->code }} · {{ $s->academicSession?->name }}</div>
                            <div class="mt-1 text-[16px] font-semibold text-ink group-hover:text-brand">{{ $s->name }}</div>
                        </div>
                        <x-ui.ring :value="$pct" :size="52" :stroke="5" :tone="$pct >= 95 ? 'brass' : 'brand'" />
                    </div>
                    <div class="mt-4 grid grid-cols-3 gap-2 border-t border-line pt-3 text-[12px]">
                        <div><div class="eyebrow !text-[9.5px]">Students</div><div class="mono mt-1 text-ink">{{ $s->students_count }}/{{ $s->capacity }}</div></div>
                        <div><div class="eyebrow !text-[9.5px]">Courses</div><div class="mono mt-1 text-ink">{{ $s->courses_count }}</div></div>
                        <div><div class="eyebrow !text-[9.5px]">Room</div><div class="mt-1 truncate text-ink">{{ $s->room }}</div></div>
                    </div>
                    <div class="mt-3 flex items-center gap-2 text-[12px] text-muted">
                        <x-ui.avatar :name="$s->incharge?->name ?? '?'" :size="22" />
                        <span class="truncate">{{ $s->incharge?->name ?? 'No incharge assigned' }}</span>
                    </div>
                </a>
            @endforeach
        </div>
    @empty
        <div class="panel"><x-ui.empty icon="layers" title="No sections found" description="Clear the filters, or create a section for the current session.">
            <a href="{{ route('academics.sections.index') }}" class="btn btn-ghost">Clear filters</a>
            <a href="{{ route('academics.sections.create') }}" class="btn btn-primary"><x-icon name="plus" :size="15" /> New section</a>
        </x-ui.empty></div>
    @endforelse
</x-layouts.app>
