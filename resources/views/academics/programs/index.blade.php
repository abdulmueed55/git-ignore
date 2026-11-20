<x-layouts.app title="Programs">
    <x-ui.page-header eyebrow="Academics" title="Programs" description="Intermediate, associate degree and BS programs offered by the college, with seats and enrolment.">
        <x-slot:actions>
            <a href="{{ route('academics.programs.create') }}" class="btn btn-primary"><x-icon name="plus" :size="16" /> New program</a>
        </x-slot:actions>
    </x-ui.page-header>

    <div class="mb-6 grid gap-3 sm:grid-cols-3">
        <x-ui.stat label="Active programs" :value="$totals['programs']" icon="book" />
        <x-ui.stat label="Seats offered" :value="number_format($totals['seats'])" icon="layers" hint="per intake" />
        <x-ui.stat label="Students enrolled" :value="number_format($totals['enrolled'])" icon="users" :hint="$totals['seats'] ? round($totals['enrolled'] / $totals['seats'] * 100).'% of seats' : null" />
    </div>

    @forelse ($programs->groupBy('level') as $level => $items)
        <div class="mb-3 mt-8 flex items-center gap-3 first:mt-0">
            <span class="eyebrow">{{ \App\Models\Program::LEVELS[$level] ?? $level }}</span>
            <span class="h-px flex-1 bg-line"></span>
            <span class="mono text-[11px] text-muted">{{ $items->count() }}</span>
        </div>
        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            @foreach ($items as $p)
                @php
                    $fill = $p->seats ? min(100, round($p->students_count / $p->seats * 100)) : 0;
                    $w = $p->weights();
                @endphp
                <a href="{{ route('academics.programs.show', $p) }}" class="panel group flex flex-col p-5 transition hover:border-line-strong {{ $p->is_active ? '' : 'opacity-60' }}">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <div class="mono text-[11.5px] text-brass">{{ $p->code }}</div>
                            <div class="display mt-1 text-[19px] leading-snug text-ink group-hover:text-brand">{{ $p->name }}</div>
                            <div class="mt-0.5 text-[12px] text-muted">{{ $p->department?->name }} · {{ $p->duration_years }} {{ Str::plural('year', $p->duration_years) }} · {{ ucfirst($p->shift) }}</div>
                        </div>
                        @unless ($p->is_active)<span class="badge badge-warn">Inactive</span>@endunless
                    </div>
                    <div class="mt-5">
                        <div class="flex items-baseline justify-between text-[12px]">
                            <span class="text-muted">Enrolled vs seats</span>
                            <span><span class="numeral text-[18px] text-ink">{{ $p->students_count }}</span><span class="text-muted"> / {{ $p->seats }}</span></span>
                        </div>
                        <div class="mt-1.5 h-2 overflow-hidden rounded-full bg-sunken">
                            <div class="h-full rounded-full {{ $fill >= 95 ? 'bg-brass' : 'bg-brand' }}" style="width: {{ $fill }}%"></div>
                        </div>
                    </div>
                    <dl class="mt-5 grid grid-cols-3 gap-2 border-t border-line pt-4 text-[12px]">
                        <div><dt class="eyebrow !text-[9.5px]">Sections</dt><dd class="numeral mt-1 text-[18px]">{{ $p->sections_count }}</dd></div>
                        <div><dt class="eyebrow !text-[9.5px]">Min. marks</dt><dd class="numeral mt-1 text-[18px]">{{ rtrim(rtrim(number_format($p->min_percentage, 1), '0'), '.') }}<span class="text-[11px] text-muted">%</span></dd></div>
                        <div><dt class="eyebrow !text-[9.5px]">Annual fee</dt><dd class="numeral mt-1 text-[18px]">{{ number_format($p->annual_fee / 1000, 1) }}<span class="text-[11px] text-muted">k</span></dd></div>
                    </dl>
                    <div class="mt-4 flex flex-wrap gap-1.5 text-[11px]">
                        <span class="badge badge-plain">{{ $p->basedOnInter() ? 'Inter '.($w['academic'] * 100).'% + Matric '.(($w['matric'] ?? 0) * 100).'%' : 'Matric marks 100%' }}</span>
                        <span class="badge badge-brass badge-plain">Hafiz +{{ $w['hafiz_bonus'] }}</span>
                    </div>
                </a>
            @endforeach
        </div>
    @empty
        <div class="panel"><x-ui.empty icon="book" title="No programs yet" description="Create the first program to start defining sections, subjects and admissions.">
            <a href="{{ route('academics.programs.create') }}" class="btn btn-primary"><x-icon name="plus" :size="15" /> New program</a>
        </x-ui.empty></div>
    @endforelse
</x-layouts.app>
