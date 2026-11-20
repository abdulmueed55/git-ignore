<x-layouts.app title="Attendance">
    @php
        $prev = $date->copy()->subDay(); while ($prev->isWeekend()) { $prev->subDay(); }
        $next = $date->copy()->addDay(); while ($next->isWeekend()) { $next->addDay(); }
        $stateBadge = [
            'marked' => ['ok', 'Marked'], 'now' => ['info', 'In progress'], 'overdue' => ['bad', 'Not marked'],
            'upcoming' => ['', 'Upcoming'], 'holiday' => ['warn', 'Holiday'],
        ];
    @endphp

    <x-ui.page-header eyebrow="Attendance" :title="$isToday ? 'Today’s registers' : 'Registers · '.$date->format('D, j M Y')"
        :description="$user->isLeadership() ? 'Every period across the college — what has been marked, what is running and what is overdue.' : 'Your periods for the day. Tap a period to open its register; everyone starts as present.'">
        <x-slot:actions>
            <div class="flex items-center gap-1">
                <a href="{{ route('attendance.index', ['date' => $prev->toDateString()]) }}" class="btn btn-ghost btn-icon" title="Previous working day"><x-icon name="arrow-left" :size="16" /></a>
                <form method="GET" class="flex items-center gap-1">
                    <input type="date" name="date" value="{{ $date->toDateString() }}" max="{{ today()->addDays(7)->toDateString() }}" class="input !h-9 !w-[150px]" onchange="this.form.submit()" aria-label="Choose date">
                </form>
                @if (! $isToday)
                    <a href="{{ route('attendance.index', ['date' => $next->toDateString()]) }}" class="btn btn-ghost btn-icon" title="Next working day"><x-icon name="arrow-right" :size="16" /></a>
                    <a href="{{ route('attendance.index') }}" class="btn btn-ghost">Today</a>
                @endif
            </div>
            <a href="{{ route('attendance.register') }}" class="btn btn-ghost"><x-icon name="printer" :size="16" /> Monthly register</a>
        </x-slot:actions>
    </x-ui.page-header>

    @if ($holiday)
        <div class="stripe-brass mb-6 flex items-center gap-3 rounded-xl border border-brass/30 px-4 py-3 text-[13px]">
            <x-icon name="calendar" class="text-brass" /> <span><strong class="text-ink">{{ $holiday }}</strong> — gazetted holiday. No registers are expected today.</span>
        </div>
    @endif

    {{-- KPI strip --}}
    <div class="mb-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <div class="panel flex items-center gap-5 p-5">
            <x-ui.ring :value="$summary['compliance'] ?? 0" :size="92" :stroke="8" :tone="($summary['compliance'] ?? 100) >= 90 ? 'ok' : (($summary['compliance'] ?? 0) >= 70 ? 'warn' : 'bad')" />
            <div>
                <div class="eyebrow">Registers marked</div>
                <div class="numeral mt-2 text-[30px]">{{ $summary['marked'] }}<span class="text-[16px] text-muted"> / {{ $summary['due'] }}</span></div>
                <div class="mt-1 text-[12px] text-muted">{{ $summary['upcoming'] }} still to come</div>
            </div>
        </div>
        <x-ui.stat label="Present on the day" :value="$dayTotals['rate'] !== null ? number_format($dayTotals['rate'], 1) : '—'" :suffix="$dayTotals['rate'] !== null ? '%' : null" icon="users"
                   :hint="$dayTotals['lectures'] ? number_format($dayTotals['present']).' of '.number_format($dayTotals['counted']).' student-periods' : 'Nothing marked yet'" />
        <x-ui.stat label="Overdue registers" :value="$summary['overdue']" icon="clock" :hint="$summary['overdue'] ? 'period ended, not marked' : 'all caught up'"
                   class="{{ $summary['overdue'] ? '!border-bad/40' : '' }}" />
        <x-ui.stat label="Below {{ config('attendance.threshold') }}%" :value="$atRisk" icon="alert" :href="route('attendance.defaulters')"
                   :hint="$user->isLeadership() ? 'students college-wide' : 'students in your classes'" />
    </div>

    @if ($items->isEmpty())
        <div class="panel">
            <x-ui.empty icon="calendar" :title="$date->isWeekend() ? 'No classes on '.$date->format('l') : 'No periods on the timetable for this day'"
                        :description="$user->isLeadership() ? 'The timetable has no periods scheduled for this date.' : 'You have no periods scheduled. Choose another date, or open a monthly register.'">
                <a href="{{ route('attendance.index', ['date' => $prev->toDateString()]) }}" class="btn btn-ghost"><x-icon name="arrow-left" :size="15" /> {{ $prev->format('l, j M') }}</a>
                <a href="{{ route('attendance.register') }}" class="btn btn-primary">Open monthly register</a>
            </x-ui.empty>
        </div>
    @elseif ($board)
        <div class="grid gap-6 xl:grid-cols-[1fr_320px]">
            {{-- Leadership register board --}}
            <div class="panel overflow-hidden">
                <div class="panel-head">
                    <div>
                        <div class="panel-title">Register board</div>
                        <div class="text-[12px] text-muted">Rows are classes, columns are periods. Click any cell to open that register.</div>
                    </div>
                    <div class="hidden items-center gap-3 text-[11.5px] text-muted md:flex">
                        <span class="flex items-center gap-1.5"><i class="h-2.5 w-2.5 rounded-sm bg-ok"></i>Marked</span>
                        <span class="flex items-center gap-1.5"><i class="h-2.5 w-2.5 rounded-sm bg-info"></i>Running</span>
                        <span class="flex items-center gap-1.5"><i class="h-2.5 w-2.5 rounded-sm bg-bad"></i>Overdue</span>
                        <span class="flex items-center gap-1.5"><i class="h-2.5 w-2.5 rounded-sm border border-line-strong bg-sunken"></i>Upcoming</span>
                    </div>
                </div>
                <div class="scrollbar-thin overflow-x-auto">
                    <table class="w-full min-w-[640px] border-separate border-spacing-0 text-[12px]">
                        <thead>
                            <tr>
                                <th class="sticky left-0 z-10 border-b border-line bg-surface px-4 py-2.5 text-left"><span class="eyebrow">Class</span></th>
                                @foreach ($board['periods'] as $p)
                                    <th class="border-b border-line px-1.5 py-2.5 text-center"><span class="mono text-[11px] font-medium text-muted">{{ $p }}</span></th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($board['rows'] as $row)
                                <tr>
                                    <td class="sticky left-0 z-10 border-b border-line bg-surface px-4 py-2 whitespace-nowrap">
                                        <div class="font-semibold text-ink">{{ $row['section']->program->code }}</div>
                                        <div class="text-[11px] text-muted">{{ $row['section']->name }} · {{ $rosterCounts[$row['section']->id] ?? 0 }}</div>
                                    </td>
                                    @foreach ($board['periods'] as $p)
                                        @php $cell = $row['cells'][$p] ?? null; @endphp
                                        <td class="border-b border-line px-1 py-1.5">
                                            @if ($cell)
                                                @php
                                                    $cls = match ($cell->state) {
                                                        'marked' => 'bg-ok-soft text-ok border-ok/30',
                                                        'now' => 'bg-info-soft text-info border-info/40 ring-2 ring-info/30',
                                                        'overdue' => 'bg-bad-soft text-bad border-bad/30',
                                                        'holiday' => 'bg-warn-soft text-warn border-warn/30',
                                                        default => 'bg-sunken text-muted border-line',
                                                    };
                                                    $rate = $cell->lecture?->attendanceRate();
                                                @endphp
                                                <a href="{{ route('attendance.mark', ['course' => $cell->course->id, 'date' => $date->toDateString(), 'slot' => $cell->slot->id]) }}"
                                                   class="block rounded-md border px-2 py-1.5 leading-tight transition hover:-translate-y-px hover:shadow-sm {{ $cls }}"
                                                   title="{{ $cell->course->subject->name }} — {{ $cell->course->teacher?->name }}">
                                                    <div class="mono truncate text-[11px] font-semibold">{{ $cell->course->subject->code }}</div>
                                                    <div class="mono text-[10.5px] opacity-80">{{ $rate !== null ? $rate.'%' : $stateBadge[$cell->state][1] }}</div>
                                                </a>
                                            @endif
                                        </td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="space-y-6">
                <div class="panel">
                    <div class="panel-head"><div class="panel-title">Present by program</div><span class="eyebrow">{{ $date->format('j M') }}</span></div>
                    <div class="space-y-3 p-4">
                        @forelse ($byProgram as $p)
                            <div>
                                <div class="mb-1 flex justify-between text-[12.5px]"><span class="font-semibold text-ink-2">{{ $p['label'] }}</span><span class="mono {{ $p['rate'] < config('attendance.threshold') ? 'text-bad' : 'text-ink' }}">{{ $p['rate'] }}%</span></div>
                                <div class="h-1.5 overflow-hidden rounded-full bg-sunken"><div class="h-full rounded-full {{ $p['rate'] < config('attendance.threshold') ? 'bg-bad' : 'bg-brand' }}" style="width: {{ $p['rate'] }}%"></div></div>
                            </div>
                        @empty
                            <p class="py-4 text-center text-[12.5px] text-muted">No registers marked yet for this date.</p>
                        @endforelse
                    </div>
                </div>
                <div class="panel">
                    <div class="panel-head"><div class="panel-title">Overdue registers</div><span class="badge {{ $summary['overdue'] ? 'badge-bad' : 'badge-ok' }}">{{ $summary['overdue'] }}</span></div>
                    <ul class="divide-y divide-line">
                        @forelse ($items->where('state', 'overdue')->take(8) as $i)
                            <li class="flex items-center gap-3 px-4 py-2.5">
                                <x-ui.avatar :name="$i->course->teacher?->name ?? '?'" :size="28" />
                                <div class="min-w-0 flex-1 leading-tight">
                                    <div class="truncate text-[12.5px] font-semibold text-ink">{{ $i->course->subject->name }} · {{ $i->course->section->label() }}</div>
                                    <div class="truncate text-[11.5px] text-muted">{{ $i->course->teacher?->name }} · {{ substr($i->slot->starts_at, 0, 5) }}</div>
                                </div>
                                <a href="{{ route('attendance.mark', ['course' => $i->course->id, 'date' => $date->toDateString(), 'slot' => $i->slot->id]) }}" class="btn btn-ghost btn-sm">Open</a>
                            </li>
                        @empty
                            <li class="flex items-center gap-2 px-4 py-5 text-[12.5px] text-muted"><x-icon name="check" class="text-ok" :size="16" /> Every finished period has been marked.</li>
                        @endforelse
                    </ul>
                </div>
            </div>
        </div>
    @else
        {{-- Teacher timeline --}}
        <div class="panel">
            <div class="panel-head">
                <div class="panel-title">{{ $date->format('l') }} · {{ $items->count() }} {{ \Illuminate\Support\Str::plural('period', $items->count()) }}</div>
                <span class="eyebrow">{{ $isToday ? 'Now '.now()->format('H:i') : $date->format('j M Y') }}</span>
            </div>
            <ol class="relative p-2 sm:p-4">
                @foreach ($items as $i)
                    @php
                        [$tone, $label] = $stateBadge[$i->state];
                        $rate = $i->lecture?->attendanceRate();
                        $markUrl = route('attendance.mark', ['course' => $i->course->id, 'date' => $date->toDateString(), 'slot' => $i->slot->id]);
                    @endphp
                    <li class="group relative grid grid-cols-[64px_1fr] gap-3 sm:grid-cols-[84px_1fr] sm:gap-5">
                        <div class="pt-4 text-right">
                            <div class="mono text-[13px] font-semibold text-ink">{{ substr($i->slot->starts_at, 0, 5) }}</div>
                            <div class="mono text-[11px] text-muted">{{ substr($i->slot->ends_at, 0, 5) }}</div>
                        </div>
                        <div class="relative border-l border-line pb-3 pl-5 sm:pl-6">
                            <span class="absolute -left-[6px] top-[22px] h-[11px] w-[11px] rounded-full border-2 border-surface
                                {{ match($i->state) { 'marked' => 'bg-ok', 'now' => 'bg-info animate-pulse', 'overdue' => 'bg-bad', 'holiday' => 'bg-warn', default => 'bg-line-strong' } }}"></span>
                            <a href="{{ $i->state === 'upcoming' && ! $isToday ? '#' : $markUrl }}"
                               class="block rounded-xl border p-4 transition {{ $i->state === 'now' ? 'border-info/50 bg-info-soft/40' : ($i->state === 'overdue' ? 'border-bad/30 hover:border-bad/60' : 'border-line hover:border-line-strong') }} hover:bg-sunken/40">
                                <div class="flex flex-wrap items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <div class="text-[15px] font-semibold text-ink">{{ $i->course->subject->name }}</div>
                                        <div class="mt-0.5 text-[12.5px] text-muted">{{ $i->course->section->label() }} · Room {{ $i->slot->room ?? $i->course->section->room }} · {{ $rosterCounts[$i->course->section_id] ?? 0 }} students</div>
                                    </div>
                                    <span class="badge {{ $tone ? 'badge-'.$tone : '' }}">{{ $label }}{{ $rate !== null ? ' · '.$rate.'%' : '' }}</span>
                                </div>
                                @if ($i->lecture)
                                    <div class="mt-3 flex flex-wrap items-center gap-x-4 gap-y-1 text-[12px]">
                                        <span class="text-ink-2"><span class="text-muted">Topic:</span> {{ $i->lecture->topic }}</span>
                                        <span class="mono text-ok">P {{ $i->lecture->present_count }}</span>
                                        <span class="mono text-bad">A {{ $i->lecture->absent_count }}</span>
                                        <span class="mono text-warn">L {{ $i->lecture->late_count }}</span>
                                        <span class="mono text-info">E {{ $i->lecture->leave_count }}</span>
                                        @if ($i->lecture->isLocked())<span class="ml-auto flex items-center gap-1 text-muted"><x-icon name="lock" :size="12" /> Locked</span>@endif
                                    </div>
                                @elseif (in_array($i->state, ['now', 'overdue']))
                                    <div class="mt-3 inline-flex items-center gap-1.5 text-[12.5px] font-semibold {{ $i->state === 'now' ? 'text-info' : 'text-bad' }}">Mark attendance <x-icon name="arrow-right" :size="14" /></div>
                                @endif
                            </a>
                        </div>
                    </li>
                @endforeach
            </ol>
        </div>
    @endif
</x-layouts.app>
