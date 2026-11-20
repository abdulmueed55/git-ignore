<x-layouts.app :title="$student->name.' · Attendance'">
    @php
        $rate = $overall['rate'];
        $tone = $rate === null ? 'muted' : ($rate < $threshold ? 'bad' : ($rate < $threshold + config('attendance.warning_margin') ? 'warn' : 'ok'));
        $start = $month->copy()->startOfMonth();
        $gridStart = $start->copy()->startOfWeek();
        $gridEnd = $month->copy()->endOfMonth()->endOfWeek();
        $dayCls = ['P' => 'bg-ok text-white', 'M' => 'bg-warn-soft text-warn', 'A' => 'bg-bad text-white', 'E' => 'bg-info-soft text-info'];
        $prevMonth = $month->copy()->subMonth()->format('Y-m');
        $nextMonth = $month->copy()->addMonth();
    @endphp

    <x-ui.page-header eyebrow="Attendance profile" :title="$student->name"
        :description="'s/o '.$student->father_name.' · '.$student->roll_no.' · '.($student->section?->label() ?? 'No class')">
        <x-slot:actions>
            @if (Route::has('students.show'))
                <a href="{{ route('students.show', $student) }}" class="btn btn-ghost"><x-icon name="id" :size="15" /> Student record</a>
            @endif
            <a href="{{ route('attendance.leaves.index') }}#file" class="btn btn-ghost"><x-icon name="leaf" :size="15" /> File leave</a>
        </x-slot:actions>
    </x-ui.page-header>

    <div class="mb-6 grid gap-4 lg:grid-cols-[300px_1fr]">
        <div class="panel flex flex-col items-center justify-center p-6 text-center">
            <x-ui.ring :value="$rate ?? 0" :size="150" :stroke="12" :tone="$tone" label="overall" />
            <div class="mt-4 text-[13px]">
                @if ($rate === null)
                    <span class="text-muted">No registers recorded yet.</span>
                @elseif ($rate < $threshold)
                    <span class="font-semibold text-bad">Short of attendance</span> — needs {{ $threshold }}% to sit exams.
                @else
                    <span class="font-semibold text-ok">Eligible</span> — above the {{ $threshold }}% requirement.
                @endif
            </div>
            <div class="mt-4 grid w-full grid-cols-4 gap-2 border-t border-line pt-4 text-[11px] text-muted">
                <div><div class="mono text-[16px] font-semibold text-ok">{{ $overall['P'] }}</div>Present</div>
                <div><div class="mono text-[16px] font-semibold text-warn">{{ $overall['L'] }}</div>Late</div>
                <div><div class="mono text-[16px] font-semibold text-bad">{{ $overall['A'] }}</div>Absent</div>
                <div><div class="mono text-[16px] font-semibold text-info">{{ $overall['E'] }}</div>Leave</div>
            </div>
        </div>

        <div class="grid gap-4 sm:grid-cols-3">
            <x-ui.stat label="Longest absence" :value="$streak['longest']" suffix="days" icon="alert" :hint="$streak['current'] ? 'absent for the last '.$streak['current'].' day(s)' : 'not currently absent'" />
            <x-ui.stat label="Last present" :value="$streak['last_present'] ? \Carbon\Carbon::parse($streak['last_present'])->format('j M') : '—'" icon="calendar"
                       :hint="$streak['last_present'] ? (\Carbon\Carbon::parse($streak['last_present'])->isToday() ? 'today' : \Carbon\Carbon::parse($streak['last_present'])->diffForHumans(today())) : 'never marked present'" />
            <div class="panel p-5">
                <div class="eyebrow">12-week trend</div>
                <div class="mt-4"><x-ui.sparkline :values="$weekly" :width="220" :height="54" :tone="$tone === 'muted' ? 'brand' : $tone" class="w-full" /></div>
                <div class="mt-2 text-[12px] text-muted">Weekly attendance, oldest → newest</div>
            </div>
            <div class="panel sm:col-span-3">
                <div class="panel-head"><div class="panel-title">Guardian contact</div></div>
                <div class="grid gap-4 p-4 text-[13px] sm:grid-cols-3">
                    <div><div class="eyebrow mb-1">Guardian phone</div><div class="mono text-ink">{{ $student->guardian_phone ?: '—' }}</div></div>
                    <div><div class="eyebrow mb-1">Student phone</div><div class="mono text-ink">{{ $student->phone ?: '—' }}</div></div>
                    <div><div class="eyebrow mb-1">Class in-charge</div><div class="text-ink">{{ $student->section?->incharge?->name ?? '—' }}</div></div>
                </div>
            </div>
        </div>
    </div>

    <div class="mb-6 grid gap-6 xl:grid-cols-[1fr_380px]">
        <div class="panel overflow-hidden">
            <div class="panel-head"><div class="panel-title">By subject</div><span class="eyebrow">lowest first</span></div>
            <table class="table">
                <thead><tr><th>Subject</th><th>Teacher</th><th class="num">Present</th><th class="num">Absent</th><th class="num">Leave</th><th>Rate</th></tr></thead>
                <tbody>
                    @forelse ($subjects as $s)
                        <tr>
                            <td class="font-semibold text-ink">{{ $s['name'] }}</td>
                            <td class="text-[12.5px]">{{ $s['teacher'] ?? '—' }}</td>
                            <td class="num mono">{{ $s['P'] }}</td>
                            <td class="num mono {{ $s['A'] ? 'text-bad' : '' }}">{{ $s['A'] }}</td>
                            <td class="num mono">{{ $s['E'] }}</td>
                            <td class="w-[30%]">
                                <div class="flex items-center gap-2">
                                    <div class="h-1.5 flex-1 overflow-hidden rounded-full bg-sunken"><div class="h-full rounded-full {{ ($s['rate'] ?? 0) < $threshold ? 'bg-bad' : 'bg-ok' }}" style="width: {{ $s['rate'] ?? 0 }}%"></div></div>
                                    <span class="mono w-12 text-right text-[12px] {{ ($s['rate'] ?? 0) < $threshold ? 'text-bad' : '' }}">{{ $s['rate'] ?? '—' }}%</span>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="py-8 text-center text-muted">No registers recorded for this student yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="panel">
            <div class="panel-head">
                <div class="panel-title">{{ $month->format('F Y') }}</div>
                <div class="flex gap-1">
                    <a href="{{ route('attendance.student', [$student, 'month' => $prevMonth]) }}" class="btn btn-ghost btn-sm btn-icon !w-8"><x-icon name="arrow-left" :size="14" /></a>
                    @if ($nextMonth->lte(today()))
                        <a href="{{ route('attendance.student', [$student, 'month' => $nextMonth->format('Y-m')]) }}" class="btn btn-ghost btn-sm btn-icon !w-8"><x-icon name="arrow-right" :size="14" /></a>
                    @endif
                </div>
            </div>
            <div class="p-4">
                <div class="mb-2 grid grid-cols-7 gap-1.5 text-center text-[10.5px] font-semibold uppercase tracking-wider text-muted">
                    @foreach (['M', 'T', 'W', 'T', 'F', 'S', 'S'] as $d)<span>{{ $d }}</span>@endforeach
                </div>
                <div class="grid grid-cols-7 gap-1.5">
                    @for ($d = $gridStart->copy(); $d->lte($gridEnd); $d->addDay())
                        @php $st = $days[$d->toDateString()] ?? null; $in = $d->month === $month->month; $hol = config('attendance.holidays')[$d->toDateString()] ?? null; @endphp
                        <div class="grid aspect-square place-items-center rounded-md text-[12px] font-medium
                            {{ ! $in ? 'opacity-25' : '' }} {{ $st ? $dayCls[$st] : ($hol ? 'stripe-brass text-brass' : ($d->isWeekend() || $d->isFuture() ? 'text-muted/60' : 'bg-sunken text-muted')) }}
                            {{ $d->isToday() ? 'ring-2 ring-brass' : '' }}"
                             title="{{ $d->format('D j M') }}{{ $st ? ' — '.['P' => 'Present', 'M' => 'Partly absent', 'A' => 'Absent all day', 'E' => 'On leave'][$st] : ($hol ? ' — '.$hol : '') }}">{{ $d->day }}</div>
                    @endfor
                </div>
                <div class="mt-4 flex flex-wrap gap-3 text-[11px] text-muted">
                    <span class="flex items-center gap-1.5"><i class="h-2.5 w-2.5 rounded-sm bg-ok"></i>Present</span>
                    <span class="flex items-center gap-1.5"><i class="h-2.5 w-2.5 rounded-sm bg-warn-soft ring-1 ring-warn/40"></i>Missed some</span>
                    <span class="flex items-center gap-1.5"><i class="h-2.5 w-2.5 rounded-sm bg-bad"></i>Absent</span>
                    <span class="flex items-center gap-1.5"><i class="h-2.5 w-2.5 rounded-sm bg-info-soft ring-1 ring-info/40"></i>Leave</span>
                </div>
            </div>
        </div>
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="panel">
            <div class="panel-head"><div class="panel-title">Recent absences</div></div>
            <ul class="divide-y divide-line">
                @forelse ($absences as $a)
                    <li class="px-4 py-2.5">
                        <div class="flex justify-between text-[12.5px]"><span class="font-semibold text-ink">{{ $a->name }}</span><span class="mono text-muted">{{ \Carbon\Carbon::parse($a->held_on)->format('D j M') }} · {{ substr($a->starts_at, 0, 5) }}</span></div>
                        <div class="truncate text-[11.5px] text-muted">{{ $a->topic }}</div>
                    </li>
                @empty
                    <li class="px-4 py-6 text-center text-[12.5px] text-muted">No absences recorded. </li>
                @endforelse
            </ul>
        </div>
        <div class="panel">
            <div class="panel-head"><div class="panel-title">Guardian alerts</div></div>
            <ul class="divide-y divide-line">
                @forelse ($student->alerts as $al)
                    <li class="px-4 py-2.5">
                        <div class="flex items-center justify-between gap-2"><span class="text-[12.5px] font-semibold text-ink">{{ \App\Services\Attendance\AlertService::KINDS[$al->kind] ?? $al->kind }}</span><x-ui.status :value="$al->status" /></div>
                        <div class="mt-1 line-clamp-2 text-[11.5px] text-muted">{{ $al->message }}</div>
                        <div class="mono mt-1 text-[10.5px] text-muted">{{ $al->to }} · {{ ($al->sent_at ?? $al->created_at)->format('j M, H:i') }}</div>
                    </li>
                @empty
                    <li class="px-4 py-6 text-center text-[12.5px] text-muted">No alerts have been sent to this guardian.</li>
                @endforelse
            </ul>
        </div>
        <div class="panel">
            <div class="panel-head"><div class="panel-title">Leave history</div></div>
            <ul class="divide-y divide-line">
                @forelse ($student->leaveRequests as $l)
                    <li class="px-4 py-2.5">
                        <div class="flex items-center justify-between gap-2"><span class="mono text-[12px] text-ink">{{ $l->from_date->format('j M') }}{{ $l->days() > 1 ? ' – '.$l->to_date->format('j M') : '' }}</span><x-ui.status :value="$l->status" /></div>
                        <div class="mt-1 text-[12px] text-muted">{{ $l->reason }}</div>
                    </li>
                @empty
                    <li class="px-4 py-6 text-center text-[12.5px] text-muted">No leave requests on file.</li>
                @endforelse
            </ul>
        </div>
    </div>
</x-layouts.app>
