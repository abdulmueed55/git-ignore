<x-layouts.app title="Admissions desk">
    @if (! $cycle)
        <x-ui.page-header eyebrow="Admissions" title="Admissions desk" />
        <div class="panel">
            <x-ui.empty title="No admission cycle yet" description="Create an admission cycle to start taking applications online." icon="calendar">
                <a href="{{ route('admissions.cycles.create') }}" class="btn btn-primary"><x-icon name="plus" :size="15" /> New cycle</a>
            </x-ui.empty>
        </div>
    @else
    @php
        $total = max(1, $cycle->opens_at->diffInSeconds($cycle->closes_at));
        $elapsed = min(100, max(0, $cycle->opens_at->diffInSeconds(now(), false) / $total * 100));
        $isOpen = $cycle->isAcceptingApplications();
        $top = max(1, $funnel[0][1]);
    @endphp

    <x-ui.page-header eyebrow="Admissions · {{ $cycle->academicSession?->name }}" :title="$cycle->title"
        description="{{ $cycle->opens_at->format('j M Y') }} → {{ $cycle->closes_at->format('j M Y, g:i a') }} · application fee Rs {{ number_format($cycle->application_fee) }}">
        <x-slot:actions>
            <form method="GET" class="flex items-center gap-2">
                <select name="cycle" class="input !h-9 !w-auto min-w-[220px]" data-autosubmit>
                    @foreach ($cycles as $c)
                        <option value="{{ $c->id }}" @selected($c->id === $cycle->id)>{{ $c->title }}</option>
                    @endforeach
                </select>
            </form>
            <a href="{{ route('admissions.index', ['cycle' => $cycle->id]) }}" class="btn btn-ghost"><x-icon name="files" :size="15" /> Applications</a>
            <a href="{{ route('admissions.verification') }}" class="btn btn-brass"><x-icon name="scan" :size="15" /> Verification queue
                @if ($health['mismatch'] + $health['review'])<span class="mono rounded bg-black/10 px-1.5 text-[11px]">{{ $health['mismatch'] + $health['review'] }}</span>@endif
            </a>
        </x-slot:actions>
    </x-ui.page-header>

    <x-admissions.subnav />

    {{-- Row 1: window + funnel --}}
    <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
        <div class="panel relative overflow-hidden p-5">
            <div class="flex items-center justify-between">
                <div class="eyebrow">Application window</div>
                <x-ui.status :value="$isOpen ? 'open' : ($cycle->status === 'open' ? 'pending' : 'closed')" :label="$cycle->stateLabel()" />
            </div>
            <div class="mt-4 flex items-baseline gap-2">
                @if ($isOpen)
                    <span class="numeral text-[56px] text-ink">{{ $cycle->daysLeft() }}</span>
                    <span class="text-[14px] font-semibold text-muted">days left</span>
                @else
                    <span class="numeral text-[44px] text-ink">{{ $submitted->count() }}</span>
                    <span class="text-[14px] font-semibold text-muted">applications received</span>
                @endif
            </div>
            <div class="mt-5">
                <div class="relative h-2 overflow-hidden rounded-full bg-sunken">
                    <div class="h-full rounded-full bg-brand" style="width: {{ round($elapsed, 1) }}%"></div>
                </div>
                <div class="mt-2 flex justify-between text-[11.5px] text-muted">
                    <span>Opened {{ $cycle->opens_at->format('j M') }}</span>
                    <span class="mono">{{ round($elapsed) }}% elapsed</span>
                    <span>Closes {{ $cycle->closes_at->format('j M') }}</span>
                </div>
            </div>
            <div class="mt-5 grid grid-cols-3 gap-3 border-t border-line pt-4 text-center">
                <div><div class="numeral text-[22px]">{{ $funnel[0][1] - $funnel[1][1] }}</div><div class="eyebrow mt-1 !text-[9.5px]">Drafts</div></div>
                <div><div class="numeral text-[22px]">{{ $submitted->where('submitted_at', '>=', now()->subDay())->count() }}</div><div class="eyebrow mt-1 !text-[9.5px]">Last 24 h</div></div>
                <div><div class="numeral text-[22px]">{{ $submitted->where('is_hafiz', true)->count() }}</div><div class="eyebrow mt-1 !text-[9.5px]">Hafiz</div></div>
            </div>
        </div>

        <div class="panel p-5 lg:col-span-2">
            <div class="flex items-center justify-between">
                <div>
                    <div class="eyebrow">Admission funnel</div>
                    <div class="mt-1 text-[13px] text-muted">From first click to enrolled student</div>
                </div>
                <a href="{{ route('admissions.index', ['cycle' => $cycle->id]) }}" class="text-[12.5px] font-semibold text-brand hover:underline">Open table →</a>
            </div>
            <div class="mt-5 space-y-3">
                @foreach ($funnel as $i => [$label, $count, $tone])
                    @php $prev = $i ? max(1, $funnel[$i - 1][1]) : null; @endphp
                    <div class="grid grid-cols-[92px_1fr_88px] items-center gap-3">
                        <div class="text-[12.5px] font-semibold text-ink-2">{{ $label }}</div>
                        <div class="relative h-8 overflow-hidden rounded-md bg-sunken">
                            <div class="rise flex h-full items-center rounded-md px-3" style="width: {{ max(4, $count / $top * 100) }}%; background: color-mix(in oklab, var(--{{ $tone }}) 85%, transparent); animation-delay: {{ $i * 70 }}ms">
                                <span class="mono text-[12px] font-bold text-white">{{ $count }}</span>
                            </div>
                        </div>
                        <div class="mono text-right text-[11.5px] text-muted">
                            @if ($prev !== null){{ round($count / $prev * 100) }}% <span class="text-line-strong">of prev</span>@else 100% @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    {{-- Row 2: daily chart + AI health --}}
    <div class="mt-4 grid grid-cols-1 gap-4 lg:grid-cols-3">
        <div class="panel min-w-0 lg:col-span-2">
            <div class="panel-head">
                <div class="panel-title">Applications submitted per day</div>
                <span class="mono text-[12px] text-muted">peak {{ collect($daily['data'])->max() }} / day</span>
            </div>
            <div class="h-64 p-4">
                <canvas x-data="chart({ type: 'bar', labels: @js($daily['labels']), series: [{ label: 'Submitted', data: @js($daily['data']), tone: 'brand', thickness: 14 }] })"></canvas>
            </div>
        </div>

        <div class="panel p-5">
            <div class="flex items-center justify-between">
                <div class="eyebrow">AI document verification</div>
                <span class="badge badge-brass badge-plain"><x-icon name="sparkles" :size="11" /> Claude</span>
            </div>
            <div class="mt-4 flex items-center gap-5">
                <x-ui.ring :value="$health['auto_cleared'] ?? 0" :size="128" tone="ok" label="Auto-cleared" />
                <dl class="flex-1 space-y-2.5 text-[12.5px]">
                    <div class="flex justify-between"><dt class="text-muted">Documents read</dt><dd class="mono text-ink">{{ $health['read'] }}<span class="text-muted">/{{ $health['total'] }}</span></dd></div>
                    <div class="flex justify-between"><dt class="text-muted">Avg. confidence</dt><dd class="mono text-ink">{{ $health['confidence'] ?? '—' }}%</dd></div>
                    <div class="flex justify-between"><dt class="text-muted">Mismatches</dt><dd class="mono font-semibold text-bad">{{ $health['mismatch'] }}</dd></div>
                    <div class="flex justify-between"><dt class="text-muted">Needs a look</dt><dd class="mono font-semibold text-warn">{{ $health['review'] }}</dd></div>
                    <div class="flex justify-between"><dt class="text-muted">Unreadable files</dt><dd class="mono text-ink">{{ $health['failed'] }}</dd></div>
                </dl>
            </div>
            <p class="mt-4 border-t border-line pt-3 text-[12px] leading-relaxed text-muted">
                Every CNIC, B-Form, result card and domicile is read automatically and cross-checked against the form and each other.
            </p>
        </div>
    </div>

    {{-- Row 3: programme demand + attention --}}
    <div class="mt-4 grid grid-cols-1 gap-4 lg:grid-cols-3">
        <div class="panel min-w-0 overflow-hidden lg:col-span-2">
            <div class="panel-head">
                <div class="panel-title">Demand vs seats</div>
                <a href="{{ route('admissions.merit.index', ['cycle' => $cycle->id]) }}" class="text-[12.5px] font-semibold text-brand hover:underline">Merit lists →</a>
            </div>
            @if ($demand->isEmpty())
                <x-ui.empty title="No programmes in this cycle" description="Edit the cycle and choose the programmes offered." icon="book">
                    <a href="{{ route('admissions.cycles.edit', $cycle) }}" class="btn btn-ghost">Edit cycle</a>
                </x-ui.empty>
            @else
            <div class="overflow-x-auto">
                <table class="table">
                    <thead><tr><th>Programme</th><th>Applied / seats</th><th class="num">Selected</th><th class="num">Admitted</th><th class="num">Top merit</th></tr></thead>
                    <tbody>
                    @foreach ($demand as $row)
                        @php $ratio = $row['applied'] / max(1, $row['program']->seats); @endphp
                        <tr>
                            <td>
                                <div class="font-semibold text-ink">{{ $row['program']->name }}</div>
                                <div class="mono text-[11px] text-muted">{{ $row['program']->code }} · min {{ rtrim(rtrim(number_format($row['program']->min_percentage, 1), '0'), '.') }}%</div>
                            </td>
                            <td class="min-w-[200px]">
                                <div class="flex items-center gap-3">
                                    <div class="relative h-2 flex-1 overflow-hidden rounded-full bg-sunken">
                                        <div class="absolute inset-y-0 left-0 rounded-full {{ $ratio >= 1 ? 'bg-brass' : 'bg-brand' }}" style="width: {{ min(100, $ratio * 100) }}%"></div>
                                    </div>
                                    <span class="mono text-[12px]">{{ $row['applied'] }}<span class="text-muted">/{{ $row['program']->seats }}</span></span>
                                </div>
                                <div class="mt-1 text-[11px] text-muted">{{ number_format($ratio, 2) }} applicants per seat</div>
                            </td>
                            <td class="num mono">{{ $row['selected'] }}</td>
                            <td class="num mono">{{ $row['admitted'] }}</td>
                            <td class="num mono">{{ $row['top'] ? number_format($row['top'], 2) : '—' }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            @endif
        </div>

        <div class="panel">
            <div class="panel-head">
                <div class="panel-title">Needs attention</div>
                <a href="{{ route('admissions.verification') }}" class="text-[12.5px] font-semibold text-brand hover:underline">Queue →</a>
            </div>
            @forelse ($flagged as $a)
                @php $issue = $a->checks->where('overridden', false)->whereIn('result', ['mismatch', 'missing', 'partial'])->sortBy(fn ($c) => ['mismatch' => 0, 'missing' => 1, 'partial' => 2][$c->result])->first(); @endphp
                <a href="{{ route('admissions.show', $a) }}" class="flex items-start gap-3 border-b border-line px-4 py-3 last:border-0 hover:bg-sunken/50">
                    <span class="mt-1 h-2 w-2 shrink-0 rounded-full {{ $a->verification_status === 'mismatch' ? 'bg-bad' : 'bg-warn' }}"></span>
                    <div class="min-w-0 flex-1">
                        <div class="flex items-center justify-between gap-2">
                            <span class="truncate text-[13px] font-semibold text-ink">{{ $a->name }}</span>
                            <span class="mono text-[11px] text-muted">{{ $a->program?->code }}</span>
                        </div>
                        <div class="truncate text-[12px] text-muted">{{ $issue ? $issue->label.' — '.($issue->note ?? ucfirst($issue->result)) : 'Review documents' }}</div>
                    </div>
                </a>
            @empty
                <x-ui.empty title="Nothing waiting" description="Every submitted application's documents agree with the form." icon="shield" />
            @endforelse
        </div>
    </div>

    {{-- Row 4: quota mix --}}
    @if ($quotas->isNotEmpty())
    <div class="panel mt-4 p-5">
        <div class="eyebrow mb-4">Quota mix</div>
        <div class="flex h-3 overflow-hidden rounded-full">
            @php $tones = ['brand', 'brass', 'info', 'ok', 'warn', 'bad']; $sum = max(1, $quotas->sum()); @endphp
            @foreach ($quotas as $q => $n)
                <div style="width: {{ $n / $sum * 100 }}%; background: var(--{{ $tones[$loop->index % 6] }})" title="{{ $n }}"></div>
            @endforeach
        </div>
        <div class="mt-3 flex flex-wrap gap-x-6 gap-y-2 text-[12.5px]">
            @foreach ($quotas as $q => $n)
                <span class="flex items-center gap-2"><span class="h-2.5 w-2.5 rounded-sm" style="background: var(--{{ $tones[$loop->index % 6] }})"></span>{{ \App\Models\AdmissionApplication::QUOTAS[$q] ?? $q }} <span class="mono text-muted">{{ $n }}</span></span>
            @endforeach
        </div>
    </div>
    @endif
    @endif
</x-layouts.app>
