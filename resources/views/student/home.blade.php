<x-layouts.student title="Home">
    @php
        $rate = $overall['rate'];
        $tone = $rate === null ? 'muted' : ($rate < $threshold ? 'bad' : ($rate < $threshold + 5 ? 'warn' : 'ok'));
        $next = $today->first(fn ($i) => $i->end->isFuture());
    @endphp

    {{-- Who + attendance at a glance --}}
    <section class="panel overflow-hidden">
        <div class="flex flex-wrap items-center gap-5 p-5">
            <div class="min-w-0 flex-1 basis-[280px]">
                <div class="eyebrow">{{ now()->format('l, j F') }} · {{ \App\Support\Dates::hijri(now()) }}</div>
                <h1 class="mt-1 text-[24px] font-extrabold leading-tight">Assalam-o-Alaikum, {{ explode(' ', $student->name)[0] === 'Muhammad' ? (explode(' ', $student->name)[1] ?? $student->name) : explode(' ', $student->name)[0] }}</h1>
                <div class="mt-1 text-[13.5px] text-muted">
                    <span class="mono">{{ $student->roll_no }}</span> · {{ $student->section?->label() ?? $student->program?->name }} · Session {{ $student->academicSession?->name }}
                </div>
            </div>
            <a href="{{ route('student.attendance') }}" class="flex w-full items-center gap-4 rounded-md border border-line px-4 py-3 hover:border-line-strong sm:w-auto">
                <div class="relative grid h-[76px] w-[76px] place-items-center">
                    <svg width="76" height="76" class="-rotate-90"><circle cx="38" cy="38" r="32" fill="none" stroke="var(--sunken)" stroke-width="8"/><circle cx="38" cy="38" r="32" fill="none" stroke="var(--{{ $tone === 'muted' ? 'line-strong' : $tone }})" stroke-width="8" stroke-linecap="round" stroke-dasharray="201.06" stroke-dashoffset="{{ 201.06 * (1 - ($rate ?? 0) / 100) }}"/></svg>
                    <div class="absolute text-center"><span class="numeral text-[19px]">{{ $rate !== null ? round($rate) : '—' }}</span><span class="text-[11px] text-muted">%</span></div>
                </div>
                <div class="leading-tight">
                    <div class="text-[13px] font-bold">Attendance</div>
                    <div class="text-[12px] text-{{ $tone === 'muted' ? 'muted' : $tone }}">{{ $rate === null ? 'No registers yet' : ($rate < $threshold ? 'Below '.$threshold.'% — exam rule' : 'Above '.$threshold.'%') }}</div>
                    <div class="mono mt-1 text-[11.5px] text-muted">P {{ $overall['P'] + $overall['L'] }} · A {{ $overall['A'] }} · Leave {{ $overall['E'] }}</div>
                </div>
            </a>
        </div>
        @if ($rate !== null && $rate < $threshold)
            <div class="border-t border-bad/25 bg-bad-soft px-5 py-3 text-[13px] text-bad">
                Your attendance is below {{ $threshold }}%. Students below {{ $threshold }}% may not sit the exams. See <a href="{{ route('student.attendance') }}" class="font-semibold underline">which subjects</a> need attention.
            </div>
        @endif
    </section>

    <div class="mt-5 grid gap-5 lg:grid-cols-[1fr_340px]">
        <div class="min-w-0 space-y-5">
            {{-- Today --}}
            <section class="panel">
                <div class="panel-head">
                    <div class="panel-title">Today’s classes</div>
                    @if ($next)<span class="badge badge-info">Next: {{ $next->start->format('H:i') }} {{ $next->course->subject->name }}</span>@endif
                </div>
                @if ($today->isEmpty())
                    <x-ui.empty icon="calendar" title="No classes today" description="{{ now()->isWeekend() ? 'Enjoy the weekend. ' : '' }}Your week, including extra classes and tests, is on the Timetable tab.">
                        <a href="{{ route('student.timetable') }}" class="btn btn-primary btn-sm">Open timetable</a>
                    </x-ui.empty>
                @else
                    <ol class="divide-y divide-line">
                        @foreach ($today as $i)
                            @php $mark = $marks[$i->course->id.'@'.$i->start->format('H:i')] ?? null; @endphp
                            <li class="flex items-center gap-3 px-4 py-3 {{ $i->end->isPast() && ! $mark ? 'opacity-60' : '' }}">
                                <div class="w-14 shrink-0 leading-tight">
                                    <div class="mono text-[13.5px] font-semibold">{{ $i->start->format('H:i') }}</div>
                                    <div class="mono text-[11px] text-muted">{{ $i->end->format('H:i') }}</div>
                                </div>
                                <div class="min-w-0 flex-1">
                                    <div class="flex flex-wrap items-center gap-1.5 text-[14px] font-semibold">{{ $i->course->subject->name }}
                                        @if ($i->type === 'extra')<span class="badge badge-brass">{{ $i->label }}</span>@endif
                                        @if ($i->start->isPast() && $i->end->isFuture())<span class="badge badge-info">Now</span>@endif
                                    </div>
                                    <div class="truncate text-[12.5px] text-muted">Room {{ $i->room }} · {{ $i->course->teacher?->name ?? 'Teacher to be announced' }}</div>
                                </div>
                                @if ($mark)<x-ui.status :value="$mark" :label="config('attendance.statuses')[$mark] ?? $mark" />@endif
                            </li>
                        @endforeach
                    </ol>
                @endif
            </section>

            {{-- Subjects --}}
            <section class="panel">
                <div class="panel-head"><div class="panel-title">My subjects</div><a href="{{ route('student.attendance') }}" class="text-[12.5px] font-semibold text-brand">Details →</a></div>
                @if ($courses->isEmpty())
                    <x-ui.empty icon="book" title="No subjects yet" description="Your subjects appear once the college assigns your class section. Ask the admission office if this takes more than a week.">
                        <a href="{{ route('student.application') }}" class="btn btn-ghost btn-sm">See my admission</a>
                    </x-ui.empty>
                @else
                    <ul class="grid gap-px bg-line sm:grid-cols-2">
                        @foreach ($courses as $c)
                            @php $r = $rates[$c->id] ?? null; $cr = $r['rate'] ?? null; @endphp
                            <li class="flex items-center gap-3 bg-surface px-4 py-3">
                                <div class="min-w-0 flex-1">
                                    <div class="truncate text-[13.5px] font-semibold">{{ $c->subject->name }}</div>
                                    <div class="truncate text-[12px] text-muted">{{ $c->teacher?->name ?? 'Teacher to be announced' }}</div>
                                </div>
                                <span class="mono text-[13px] font-bold {{ $cr === null ? 'text-muted' : ($cr < $threshold ? 'text-bad' : 'text-ok') }}">{{ $cr !== null ? round($cr).'%' : 'new' }}</span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>
        </div>

        <aside class="space-y-5">
            <section class="panel">
                <div class="panel-head"><div class="panel-title">Extra classes &amp; tests</div></div>
                @forelse ($upcoming as $e)
                    <div class="flex gap-3 border-b border-line px-4 py-3 last:border-0">
                        <div class="w-11 shrink-0 text-center leading-tight"><div class="numeral text-[18px]">{{ $e->held_on->format('j') }}</div><div class="eyebrow !text-[9px]">{{ $e->held_on->format('M') }}</div></div>
                        <div class="min-w-0 text-[13px]">
                            <div class="font-semibold">{{ $e->course->subject->name }} <span class="badge badge-brass">{{ $e->kindLabel() }}</span></div>
                            <div class="text-muted">{{ $e->held_on->format('D') }} {{ substr($e->starts_at, 0, 5) }}–{{ substr($e->ends_at, 0, 5) }} · Room {{ $e->room }}</div>
                            @if ($e->reason)<div class="text-ink-2">{{ $e->reason }}</div>@endif
                        </div>
                    </div>
                @empty
                    <p class="px-4 py-5 text-[13px] text-muted">No extra classes or tests scheduled. When a teacher schedules one, it appears here and on your timetable.</p>
                @endforelse
            </section>

            <section class="panel">
                <div class="panel-head"><div class="panel-title">College notices</div></div>
                @forelse ($notices as $n)
                    <a href="{{ route('portal.post', $n->slug) }}" class="block border-b border-line px-4 py-3 last:border-0 hover:bg-sunken/50">
                        <div class="text-[13px] font-semibold leading-snug">{{ $n->title }}</div>
                        <div class="mt-0.5 text-[11.5px] text-muted">{{ $n->published_at?->format('j M Y') }}</div>
                    </a>
                @empty
                    <p class="px-4 py-5 text-[13px] text-muted">No notices right now. New notices from the college appear here.</p>
                @endforelse
            </section>

            <a href="{{ route('student.leave') }}" class="panel flex items-center gap-3 p-4 hover:border-line-strong">
                <span class="grid h-10 w-10 place-items-center rounded-full bg-brand-soft text-brand"><x-icon name="leaf" :size="18" /></span>
                <span class="min-w-0 flex-1"><span class="block text-[14px] font-semibold">Ask for leave</span><span class="block text-[12.5px] text-muted">{{ $pendingLeave ? $pendingLeave.' request(s) waiting for a decision' : 'Sick or a family matter? Request it here.' }}</span></span>
                <x-icon name="chevron-right" :size="16" class="text-muted" />
            </a>
        </aside>
    </div>
</x-layouts.student>
