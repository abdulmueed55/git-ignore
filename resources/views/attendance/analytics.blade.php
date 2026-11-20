<x-layouts.app title="Attendance analytics">
    @php
        $delta = ($range['rate'] !== null && $prev['rate'] !== null) ? round($range['rate'] - $prev['rate'], 1) : null;
        $fmt = fn ($v) => $v === null ? '—' : number_format($v, 1);
    @endphp

    <x-ui.page-header eyebrow="Attendance" title="Analytics"
        :description="'Attendance from '.\Carbon\Carbon::parse($f['from'])->format('j M').' to '.\Carbon\Carbon::parse($f['to'])->format('j M Y').'. Rate = (present + late) ÷ (present + late + absent); approved leave is excluded.'">
        <x-slot:actions>
            <a href="{{ route('attendance.defaulters', request()->only('program_id', 'section_id')) }}" class="btn btn-ghost"><x-icon name="alert" :size="15" /> Defaulters</a>
        </x-slot:actions>
    </x-ui.page-header>

    {{-- Filters --}}
    <form method="GET" class="panel mb-6 flex flex-wrap items-end gap-3 p-4">
        <x-ui.field name="program_id" label="Program" type="select" :options="$options['programs']" :value="$f['program_id']" empty="All programs" class="w-full sm:w-44" />
        <x-ui.field name="section_id" label="Class" type="select" :options="$options['sections']" :value="$f['section_id']" empty="All classes" class="w-full sm:w-56" />
        <x-ui.field name="from" label="From" type="date" :value="$f['from']" class="w-[48%] sm:w-40" />
        <x-ui.field name="to" label="To" type="date" :value="$f['to']" class="w-[48%] sm:w-40" />
        <div class="flex gap-2">
            <button class="btn btn-primary"><x-icon name="filter" :size="15" /> Apply</button>
            @if (request()->hasAny(['program_id', 'section_id', 'from', 'to']))
                <a href="{{ route('attendance.analytics') }}" class="btn btn-ghost">Reset</a>
            @endif
        </div>
        <div class="ml-auto hidden gap-1 lg:flex">
            @foreach ([7 => '7d', 30 => '30d', 60 => '60d'] as $d => $l)
                <a href="{{ route('attendance.analytics', array_merge(request()->only('program_id', 'section_id'), ['from' => today()->subDays($d - 1)->toDateString(), 'to' => today()->toDateString()])) }}" class="btn btn-ghost btn-sm mono">{{ $l }}</a>
            @endforeach
        </div>
    </form>

    @if ($range['lectures'] === 0)
        <div class="panel">
            <x-ui.empty icon="chart" title="No registers in this period" description="Nothing has been marked for the selected classes and dates. Widen the date range or clear the filters.">
                <a href="{{ route('attendance.analytics') }}" class="btn btn-primary">Show last 30 days</a>
            </x-ui.empty>
        </div>
    @else
        {{-- KPIs --}}
        <div class="mb-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <x-ui.stat label="Attendance rate" :value="$fmt($range['rate'])" suffix="%" icon="chart"
                       :delta="$delta !== null ? ($delta >= 0 ? '+' : '').$delta.' pts' : null" :delta-good="($delta ?? 0) >= 0" hint="vs previous period" />
            <x-ui.stat label="Today" :value="$fmt($todayTotals['rate'])" :suffix="$todayTotals['rate'] !== null ? '%' : null" icon="clock"
                       :hint="$todayTotals['lectures'] ? $todayTotals['lectures'].' registers so far' : 'no registers yet today'" :href="route('attendance.index')" />
            <x-ui.stat label="Register compliance" :value="$fmt($compliance)" suffix="%" icon="check"
                       :hint="number_format($markedCount).' of '.number_format($scheduledCount).' timetabled periods marked'" />
            <x-ui.stat label="Below {{ $threshold }}%" :value="$below" :suffix="'/ '.$studentCount" icon="alert" :href="route('attendance.defaulters', request()->only('program_id', 'section_id'))"
                       :hint="$studentCount ? round($below / $studentCount * 100).'% of students' : null" />
        </div>

        <div class="mb-6 grid gap-6 xl:grid-cols-[1fr_340px]">
            <div class="panel">
                <div class="panel-head">
                    <div><div class="panel-title">Daily attendance</div><div class="text-[12px] text-muted">Dashed line marks the {{ $threshold }}% requirement</div></div>
                    <span class="badge badge-plain mono">{{ count($daily) }} days</span>
                </div>
                <div class="h-72 p-4">
                    <canvas x-data="chart({type:'line', suffix:'%', min:60, max:100,
                        labels: @js(array_map(fn ($d) => \Carbon\Carbon::parse($d)->format('j M'), array_keys($daily))),
                        series: [
                            {label:'Attendance', data: @js(array_values($daily)), tone:'brand'},
                            {label:'Required', data: @js(array_fill(0, count($daily), $threshold)), tone:'bad', dashed:true, fill:false}
                        ]})"></canvas>
                </div>
            </div>
            <div class="panel">
                <div class="panel-head"><div class="panel-title">Students by attendance band</div></div>
                <div class="space-y-3 p-5">
                    @php $maxBand = max(1, max($distribution)); $bandTone = ['bg-bad', 'bg-warn', 'bg-info', 'bg-brand', 'bg-ok']; @endphp
                    @foreach ($distribution as $band => $n)
                        <div class="grid grid-cols-[64px_1fr_36px] items-center gap-3 text-[12.5px]">
                            <span class="mono text-muted">{{ $band }}</span>
                            <div class="h-5 overflow-hidden rounded bg-sunken"><div class="h-full rounded {{ $bandTone[$loop->index] }}" style="width: {{ $n / $maxBand * 100 }}%"></div></div>
                            <span class="mono text-right font-semibold text-ink">{{ $n }}</span>
                        </div>
                    @endforeach
                    <p class="pt-2 text-[12px] text-muted">{{ $distribution['< 60%'] + $distribution['60–74%'] }} students are short of attendance and may be stopped from sending board / semester exam forms.</p>
                </div>
            </div>
        </div>

        <div class="mb-6 grid gap-6 lg:grid-cols-2">
            @foreach ([['By program', collect($byProgram)->mapWithKeys(fn ($r) => [$r['label'] => $r['rate']])->all(), 'Ranked by attendance'], ['Weekday pattern', $weekdays, 'Mondays and Fridays usually dip']] as [$title, $bars, $note])
                @php $floor = 50; @endphp
                <div class="panel">
                    <div class="panel-head"><div class="panel-title">{{ $title }}</div><span class="text-[12px] text-muted">{{ $note }}</span></div>
                    <div class="relative px-5 pb-4 pt-6">
                        <div class="relative h-[220px]">
                            @foreach ([100, 90, 80, 70, 60, 50] as $g)
                                <div class="absolute inset-x-0 border-t border-line" style="bottom: {{ ($g - $floor) * 2 }}%">
                                    <span class="mono absolute -top-2 right-0 bg-surface pl-1 text-[10px] text-muted">{{ $g }}%</span>
                                </div>
                            @endforeach
                            <div class="absolute inset-x-0 z-[1] border-t border-dashed border-bad/70" style="bottom: {{ ($threshold - $floor) * 2 }}%" title="{{ $threshold }}% requirement"></div>
                            <div class="absolute inset-0 flex items-end gap-3 pr-9">
                                @foreach ($bars as $label => $v)
                                    <div class="group relative flex h-full flex-1 items-end justify-center">
                                        <div class="relative w-full max-w-[46px] rounded-t-md transition-all group-hover:opacity-80 {{ $v !== null && $v < $threshold ? 'bg-bad' : ($loop->parent->first ? 'bg-brand' : 'bg-brass') }}"
                                             style="height: {{ $v !== null ? max(2, ($v - $floor) * 2) : 0 }}%">
                                            <span class="mono absolute -top-5 left-1/2 -translate-x-1/2 whitespace-nowrap text-[11px] font-semibold {{ $v !== null && $v < $threshold ? 'text-bad' : 'text-ink' }}">{{ $v !== null ? $v.'%' : '—' }}</span>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                        <div class="flex gap-3 pr-9 pt-2">
                            @foreach ($bars as $label => $v)
                                <div class="flex-1 truncate text-center text-[11.5px] font-medium text-muted">{{ $label }}</div>
                            @endforeach
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        {{-- Heatmap --}}
        <div class="panel mb-6">
            <div class="panel-head">
                <div><div class="panel-title">Attendance calendar</div><div class="text-[12px] text-muted">Each square is a working day — the greener, the better</div></div>
                <div class="flex items-center gap-1.5 text-[11px] text-muted">
                    <span>&lt;75%</span>
                    @foreach ([1, 2, 3, 4] as $lv)<span class="heat !h-3 !w-3" data-level="{{ $lv }}"></span>@endforeach
                    <span>87%+</span>
                </div>
            </div>
            <div class="scrollbar-thin overflow-x-auto p-5">
                <div class="flex gap-[6px]">
                    <div class="mr-1 grid grid-rows-5 gap-[6px] pt-5 text-[10px] text-muted">
                        @foreach (['Mon', 'Tue', 'Wed', 'Thu', 'Fri'] as $d)<span class="flex h-[26px] items-center">{{ $d }}</span>@endforeach
                    </div>
                    @foreach ($heat as $week => $days)
                        <div class="w-[26px] shrink-0">
                            <div class="mb-1 h-4 text-[10px] text-muted">{{ \Carbon\Carbon::parse($week)->day <= 7 ? \Carbon\Carbon::parse($week)->format('M') : '' }}</div>
                            <div class="grid grid-rows-5 gap-[6px]">
                                @foreach ([1, 2, 3, 4, 5] as $dow)
                                    @php $c = $days[$dow] ?? null; @endphp
                                    @if ($c && ! $c['future'])
                                        <div class="heat !h-[26px] {{ $c['holiday'] ? 'stripe-brass' : '' }}" data-level="{{ \App\Services\Attendance\AttendanceStats::level($c['rate']) }}"
                                             title="{{ \Carbon\Carbon::parse($c['date'])->format('D j M') }} — {{ $c['holiday'] ?? ($c['rate'] !== null ? $c['rate'].'%' : 'no registers') }}"></div>
                                    @else
                                        <div class="h-[26px]"></div>
                                    @endif
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        <div class="grid gap-6 lg:grid-cols-2">
            <div class="panel overflow-hidden">
                <div class="panel-head"><div class="panel-title">Classes</div><span class="eyebrow">lowest first</span></div>
                <div class="max-h-[420px] overflow-y-auto">
                    <table class="table">
                        <thead><tr><th>Class</th><th class="num">Registers</th><th>Rate</th></tr></thead>
                        <tbody>
                            @foreach (collect($bySection)->sortBy('rate') as $s)
                                <tr>
                                    <td class="font-semibold text-ink">{{ $s['label'] }}</td>
                                    <td class="num mono">{{ $s['lectures'] }}</td>
                                    <td class="w-[45%]">
                                        <div class="flex items-center gap-2">
                                            <div class="h-1.5 flex-1 overflow-hidden rounded-full bg-sunken"><div class="h-full rounded-full {{ $s['rate'] < $threshold ? 'bg-bad' : 'bg-brand' }}" style="width: {{ $s['rate'] }}%"></div></div>
                                            <span class="mono w-12 text-right text-[12px] {{ $s['rate'] < $threshold ? 'text-bad' : 'text-ink' }}">{{ $s['rate'] }}%</span>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="panel overflow-hidden">
                <div class="panel-head"><div class="panel-title">Subjects</div><span class="eyebrow">ranked</span></div>
                <div class="max-h-[420px] overflow-y-auto">
                    <table class="table">
                        <thead><tr><th class="w-8">#</th><th>Subject</th><th class="num">Registers</th><th class="num">Rate</th></tr></thead>
                        <tbody>
                            @foreach ($bySubject as $i => $s)
                                <tr>
                                    <td class="mono text-muted">{{ $i + 1 }}</td>
                                    <td class="font-medium text-ink">{{ $s['label'] }}</td>
                                    <td class="num mono">{{ $s['lectures'] }}</td>
                                    <td class="num mono font-semibold {{ $s['rate'] < $threshold ? 'text-bad' : 'text-ok' }}">{{ $s['rate'] }}%</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="panel mt-6 overflow-hidden">
            <div class="panel-head">
                <div><div class="panel-title">Teacher register compliance</div><div class="text-[12px] text-muted">Timetabled periods vs. registers actually marked in this period</div></div>
            </div>
            <div class="overflow-x-auto">
                <table class="table">
                    <thead><tr><th>Teacher</th><th class="num">Subjects</th><th class="num">Scheduled</th><th class="num">Marked</th><th class="num">Missing</th><th>Compliance</th></tr></thead>
                    <tbody>
                        @forelse ($teachers as $t)
                            <tr>
                                <td>
                                    <div class="flex items-center gap-2.5">
                                        <x-ui.avatar :name="$t['teacher']?->name ?? 'Unassigned'" :size="28" />
                                        <div class="leading-tight"><div class="font-semibold text-ink">{{ $t['teacher']?->name ?? 'Unassigned' }}</div><div class="text-[11.5px] text-muted">{{ $t['teacher']?->designation }}</div></div>
                                    </div>
                                </td>
                                <td class="num mono">{{ $t['courses'] }}</td>
                                <td class="num mono">{{ $t['scheduled'] }}</td>
                                <td class="num mono">{{ $t['marked'] }}</td>
                                <td class="num mono {{ $t['missing'] ? 'text-bad font-semibold' : 'text-muted' }}">{{ $t['missing'] }}</td>
                                <td class="w-[26%]">
                                    @if ($t['rate'] !== null)
                                        <div class="flex items-center gap-2">
                                            <div class="h-1.5 flex-1 overflow-hidden rounded-full bg-sunken"><div class="h-full rounded-full {{ $t['rate'] >= 95 ? 'bg-ok' : ($t['rate'] >= 85 ? 'bg-warn' : 'bg-bad') }}" style="width: {{ $t['rate'] }}%"></div></div>
                                            <span class="mono w-10 text-right text-[12px]">{{ $t['rate'] }}%</span>
                                        </div>
                                    @else
                                        <span class="text-[12px] text-muted">No periods scheduled</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="py-8 text-center text-muted">No teaching assignments match these filters.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</x-layouts.app>
