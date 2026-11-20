<x-layouts.app title="Merit lists">
    <x-ui.page-header eyebrow="Admissions" title="Merit lists"
        description="Lists are prepared from verified applicants above each programme's minimum, ranked by merit. Review the draft, then publish — selected applicants see it on their status page.">
        @if ($cycles->count() > 1)
            <x-slot:actions>
                <form method="GET">
                    <select name="cycle" class="input !h-9 !w-auto min-w-[240px]" onchange="this.form.submit()">
                        @foreach ($cycles as $c)
                            <option value="{{ $c->id }}" @selected($c->id === $cycle?->id)>{{ $c->title }}</option>
                        @endforeach
                    </select>
                </form>
            </x-slot:actions>
        @endif
    </x-ui.page-header>

    <x-admissions.subnav />

    @if (! $cycle || $programs->isEmpty())
        <div class="panel"><x-ui.empty title="No programmes to rank" description="Create an admission cycle and choose its programmes first." icon="trophy">
            <a href="{{ route('admissions.cycles.index') }}" class="btn btn-primary">Admission cycles</a>
        </x-ui.empty></div>
    @else
        <div class="grid grid-cols-1 gap-4 lg:grid-cols-2 2xl:grid-cols-3">
            @foreach ($programs as $row)
                @php $p = $row['program']; $filled = $p->seats - $row['seats_left']; @endphp
                <div class="panel flex flex-col">
                    <div class="p-5">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <div class="mono text-[11.5px] font-semibold text-brass">{{ $p->code }}</div>
                                <div class="display mt-1 text-[19px] leading-tight text-ink">{{ $p->name }}</div>
                            </div>
                            <x-ui.ring :value="$filled / max(1, $p->seats) * 100" :size="64" :stroke="6" />
                        </div>
                        <div class="mt-4 grid grid-cols-3 gap-2 text-center">
                            <div class="rounded-lg bg-sunken px-2 py-2"><div class="numeral text-[20px]">{{ $filled }}<span class="text-[12px] text-muted">/{{ $p->seats }}</span></div><div class="eyebrow mt-1 !text-[9px]">Seats filled</div></div>
                            <div class="rounded-lg bg-sunken px-2 py-2"><div class="numeral text-[20px]">{{ $row['candidates'] }}</div><div class="eyebrow mt-1 !text-[9px]">Eligible now</div></div>
                            <div class="rounded-lg bg-sunken px-2 py-2"><div class="numeral text-[20px]">{{ $row['lists']->count() }}</div><div class="eyebrow mt-1 !text-[9px]">Lists</div></div>
                        </div>
                    </div>

                    <ul class="flex-1 border-t border-line">
                        @forelse ($row['lists'] as $list)
                            <li>
                                <a href="{{ route('admissions.merit.show', $list) }}" class="flex items-center justify-between gap-3 border-b border-line px-5 py-3 hover:bg-sunken/50">
                                    <div>
                                        <div class="text-[13px] font-semibold text-ink">Merit list #{{ $list->list_no }}</div>
                                        <div class="text-[11.5px] text-muted">{{ $list->entries_count }} selected · closing <span class="mono">{{ $list->closing_merit ? number_format($list->closing_merit, 2) : '—' }}</span></div>
                                    </div>
                                    <x-ui.status :value="$list->status" :label="$list->status === 'published' ? 'Published '.$list->published_at?->format('j M') : 'Draft'" />
                                </a>
                            </li>
                        @empty
                            <li class="px-5 py-4 text-[12.5px] text-muted">No list yet for this programme.</li>
                        @endforelse
                    </ul>

                    <form method="POST" action="{{ route('admissions.merit.generate') }}" class="flex items-end gap-2 p-4">
                        @csrf
                        <input type="hidden" name="admission_cycle_id" value="{{ $cycle->id }}">
                        <input type="hidden" name="program_id" value="{{ $p->id }}">
                        <div class="flex-1">
                            <label class="field-label">Seats to offer</label>
                            <input type="number" name="seats" min="1" max="{{ max(1, $row['seats_left']) }}" value="{{ min($row['seats_left'], max(1, $row['candidates'])) ?: 1 }}" class="input mono" @disabled($row['seats_left'] === 0)>
                        </div>
                        <button class="btn btn-primary" @disabled($row['seats_left'] === 0 || $row['candidates'] === 0)>
                            <x-icon name="sparkles" :size="14" />
                            @if ($row['lists']->contains('status', 'draft')) Re-prepare draft #{{ $row['lists']->firstWhere('status', 'draft')->list_no }}
                            @else Prepare list #{{ $row['lists']->max('list_no') + 1 }} @endif
                        </button>
                    </form>
                </div>
            @endforeach
        </div>
    @endif
</x-layouts.app>
