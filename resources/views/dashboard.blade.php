@php
    use App\Support\Dates;
    $first = preg_replace('/^((Prof|Dr|Mr|Ms|Mrs|Hafiz)\.?\s+)+/i', '', $user->name);
    $first = explode(' ', $first)[0];
    $schedule = $schedule ?? collect();
    $toneFor = fn ($rate) => $rate === null ? 'muted' : ($rate >= 85 ? 'ok' : ($rate >= $threshold ? 'warn' : 'bad'));
@endphp
<x-layouts.app title="Command centre">

    {{-- ================= Hero ================= --}}
    <section class="panel rise relative mb-6 overflow-hidden">
        <div class="ruled pointer-events-none absolute inset-0 opacity-50 [mask-image:linear-gradient(90deg,transparent,black_60%)]"></div>
        <div class="relative grid gap-6 p-6 lg:grid-cols-[1.4fr_1fr] lg:p-8">
            <div>
                <div class="eyebrow flex flex-wrap items-center gap-2">
                    <span class="inline-block h-px w-5 bg-brass"></span>
                    {{ now()->format('l') }} · {{ Dates::hijri(now()) }}
                </div>
                <h1 class="display mt-3 text-[30px] leading-tight sm:text-[38px]">{{ Dates::greeting() }}, {{ $first }}.</h1>
                <p class="mt-3 max-w-xl text-[14.5px] leading-relaxed text-ink-2">{{ $headline }}</p>
                <div class="mt-5 flex flex-wrap gap-2">
                    @if (! $isAdmissions && Route::has('attendance.index'))
                        <a href="{{ route('attendance.index') }}" class="btn btn-primary"><x-icon name="check" :size="16" /> {{ $isTeacher ? 'Mark my registers' : 'Today’s registers' }}</a>
                    @endif
                    @if (($leadership || $isAdmissions) && Route::has('admissions.verification'))
                        <a href="{{ route('admissions.verification') }}" class="btn btn-ghost"><x-icon name="scan" :size="16" /> Review flagged applications</a>
                    @endif
                    @if ($leadership && Route::has('studio.posts.create'))
                        <a href="{{ route('studio.posts.create', ['type' => 'announcement']) }}" class="btn btn-ghost"><x-icon name="megaphone" :size="16" /> Post an announcement</a>
                    @endif
                </div>
            </div>

            @if (! $isAdmissions)
                <div class="flex items-center justify-around gap-4 rounded-xl border border-line bg-paper/60 p-4 sm:justify-between sm:p-5">
                    @if ($todayRate !== null)
                        <x-ui.ring :value="$todayRate" :size="128" :stroke="11" :tone="$toneFor($todayRate)" label="Present today" />
                    @else
                        <div class="grid h-[128px] w-[128px] place-items-center rounded-full border border-dashed border-line-strong text-center text-[12px] text-muted">No registers<br>marked yet</div>
                    @endif
                    <dl class="space-y-4 text-[12.5px]">
                        <div>
                            <dt class="eyebrow">Last 7 days</dt>
                            <dd class="mt-1 flex items-baseline gap-2"><span class="numeral text-[26px]">{{ $weekRate ?? '—' }}<span class="text-[14px] text-muted">%</span></span>
                                @if ($weekDelta !== null)<span class="mono text-[11.5px] font-semibold {{ $weekDelta >= 0 ? 'text-ok' : 'text-bad' }}">{{ $weekDelta >= 0 ? '▲' : '▼' }} {{ abs($weekDelta) }}</span>@endif
                            </dd>
                        </div>
                        <div>
                            <dt class="eyebrow">Term to date</dt>
                            <dd class="numeral mt-1 text-[26px]">{{ $termRate ?? '—' }}<span class="text-[14px] text-muted">%</span></dd>
                        </div>
                    </dl>
                </div>
            @else
                <div class="grid grid-cols-2 gap-3">
                    <div class="rounded-xl border border-line bg-paper/60 p-4">
                        <div class="eyebrow">Awaiting review</div>
                        <div class="numeral mt-2 text-[34px]">{{ $awaitingReview ?? 0 }}</div>
                    </div>
                    <div class="rounded-xl border border-line bg-paper/60 p-4">
                        <div class="eyebrow">AI auto-cleared</div>
                        <div class="numeral mt-2 text-[34px]">{{ $autoClear ?? '—' }}<span class="text-[15px] text-muted">%</span></div>
                    </div>
                </div>
            @endif
        </div>
    </section>

    {{-- ================= KPI strip ================= --}}
    <section class="mb-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @unless ($isAdmissions)
            <x-ui.stat :label="$isTeacher ? 'My students' : 'Students on roll'" :value="number_format($studentsActive)" icon="users"
                       :hint="$isTeacher ? 'across the sections you teach' : 'active enrolments, all programs'"
                       :href="Route::has('students.index') ? route('students.index') : null" />
            <x-ui.stat label="Below {{ $threshold }}% attendance" :value="$defaulterCount" icon="alert"
                       :hint="$defaulterCount ? 'need a guardian call this week' : 'no one at risk'"
                       :href="Route::has('attendance.defaulters') ? route('attendance.defaulters') : null" />
        @endunless
        @if (($leadership || $isAdmissions) && ($cycle ?? null))
            @php $daysLeft = (int) now()->diffInDays($cycle->closes_at, false); @endphp
            <x-ui.stat label="Applications · {{ $cycle->status === 'open' ? 'open cycle' : 'last cycle' }}" :value="number_format($funnel[1]['value'])" icon="inbox"
                       :hint="$cycle->status === 'open' ? ($daysLeft >= 0 ? $daysLeft.' days until '.$cycle->closes_at->format('j M') : 'closed '.$cycle->closes_at->format('j M')) : $cycle->title"
                       :href="Route::has('admissions.index') ? route('admissions.index') : null" />
            <x-ui.stat label="Flagged by document checks" :value="$flaggedCount" icon="scan"
                       :hint="$autoClear !== null ? $autoClear.'% cleared automatically' : 'AI verification'"
                       :href="Route::has('admissions.verification') ? route('admissions.verification') : null" />
        @endif
        @if ($isTeacher)
            <x-ui.stat label="Registers today" :value="$schedule->where('state', 'done')->count().'/'.$schedule->count()" icon="check"
                       :hint="$schedule->where('state', 'due')->count() ? $schedule->where('state', 'due')->count().' waiting for you' : 'all caught up'" />
            <x-ui.stat label="My courses" :value="$user->courses()->count()" icon="book" hint="subjects × sections this term"
                       :href="Route::has('academics.timetable') ? route('academics.timetable') : null" />
        @endif
        @if ($isAdmissions && ($cycle ?? null))
            <x-ui.stat label="Selected" :value="$funnel[3]['value']" icon="trophy" hint="on published merit lists" :href="Route::has('admissions.merit.index') ? route('admissions.merit.index') : null" />
            <x-ui.stat label="Admitted" :value="$funnel[4]['value']" icon="graduation" hint="converted to student records" />
        @endif
    </section>

    <div class="grid gap-6 xl:grid-cols-12">

        {{-- ================= Attendance trend ================= --}}
        @unless ($isAdmissions)
            <section class="panel xl:col-span-8">
                <div class="panel-head">
                    <div>
                        <div class="panel-title">Attendance, last six weeks</div>
                        <div class="text-[12px] text-muted">Daily share of student-lectures attended {{ $isTeacher ? 'in your classes' : 'across the college' }} · dashed line is the {{ $threshold }}% rule</div>
                    </div>
                    @if (Route::has('attendance.analytics'))<a href="{{ route('attendance.analytics') }}" class="btn btn-ghost btn-sm">Analytics <x-icon name="arrow-up-right" :size="14" /></a>@endif
                </div>
                <div class="p-4">
                    @if ($trend->count() > 1)
                        <div class="h-[260px]">
                            <canvas x-data="chart({ type: 'line', suffix: '%', min: 50, max: 100,
                                labels: @js($trend->map(fn ($r) => $r['date']->format('j M'))->values()),
                                series: [
                                    { label: 'Attendance', data: @js($trend->pluck('rate')->values()), tone: 'brand' },
                                    { label: 'Threshold', data: @js($trend->map(fn () => $threshold)->values()), tone: 'brass', dashed: true, fill: false },
                                ] })"></canvas>
                        </div>
                    @else
                        <x-ui.empty icon="chart" title="No attendance recorded yet" description="Once teachers mark their first registers, the daily trend appears here.">
                            @if (Route::has('attendance.index'))<a href="{{ route('attendance.index') }}" class="btn btn-primary btn-sm">Open today’s registers</a>@endif
                        </x-ui.empty>
                    @endif
                </div>
            </section>

            {{-- ================= Today's registers ================= --}}
            <section class="panel flex flex-col xl:col-span-4">
                <div class="panel-head">
                    <div>
                        <div class="panel-title">{{ $isTeacher ? 'My day' : 'Registers today' }}</div>
                        <div class="text-[12px] text-muted">{{ $schedule->where('state', 'done')->count() }} marked · {{ $schedule->where('state', 'due')->count() }} due · {{ $schedule->where('state', 'upcoming')->count() }} later</div>
                    </div>
                    <span class="mono text-[11px] text-muted">{{ now()->format('H:i') }}</span>
                </div>
                @php
                    $list = $isTeacher ? $schedule : $schedule->sortBy(fn ($r) => ['due' => 0, 'upcoming' => 1, 'done' => 2][$r['state']].$r['start'])->take(9);
                @endphp
                @if ($list->isEmpty())
                    <x-ui.empty icon="calendar" title="{{ now()->isWeekend() ? 'No classes today' : 'No periods scheduled' }}" description="{{ now()->isWeekend() ? 'Enjoy the weekend — registers resume on Monday.' : 'Add periods to the timetable to see them here.' }}" />
                @else
                    <ol class="scrollbar-thin max-h-[300px] flex-1 overflow-y-auto px-4 py-3">
                        @foreach ($list as $row)
                            @php $c = $row['slot']->course; @endphp
                            <li class="relative flex gap-3 pb-3 pl-5 last:pb-0">
                                <span class="absolute left-[5px] top-2 h-full w-px bg-line"></span>
                                <span class="absolute left-0 top-[7px] h-[11px] w-[11px] rounded-full border-2 {{ $row['state'] === 'done' ? 'border-ok bg-ok' : ($row['state'] === 'due' ? 'border-warn bg-warn-soft' : 'border-line-strong bg-surface') }}"></span>
                                <div class="min-w-0 flex-1">
                                    <div class="flex items-center justify-between gap-2">
                                        <span class="mono text-[11.5px] text-muted">{{ $row['start'] }}–{{ $row['end'] }}</span>
                                        @if ($row['state'] === 'done')
                                            @php $l = $row['lecture']; $tot = $l->present_count + $l->absent_count + $l->late_count + $l->leave_count; @endphp
                                            <span class="mono text-[11.5px] font-semibold text-ok">{{ $tot ? round(($l->present_count + $l->late_count) / $tot * 100) : 0 }}%</span>
                                        @elseif ($row['state'] === 'due')
                                            @if (Route::has('attendance.mark') && ($isTeacher || $leadership))
                                                <a href="{{ route('attendance.mark', ['course' => $c->id, 'date' => today()->toDateString(), 'slot' => $row['slot']->id]) }}" class="text-[11.5px] font-semibold text-warn hover:underline">Mark now →</a>
                                            @else
                                                <span class="badge badge-warn">Due</span>
                                            @endif
                                        @endif
                                    </div>
                                    <div class="truncate text-[13px] font-semibold text-ink">{{ $c->subject->name }}</div>
                                    <div class="truncate text-[12px] text-muted">{{ $c->section->program->code }} · {{ $c->section->name }} @unless($isTeacher) · {{ $c->teacher?->name }} @endunless</div>
                                </div>
                            </li>
                        @endforeach
                    </ol>
                @endif
            </section>
        @endunless

        {{-- ================= Admissions ================= --}}
        @if (($leadership || $isAdmissions) && ($cycle ?? null))
            <section class="panel xl:col-span-5">
                <div class="panel-head">
                    <div>
                        <div class="panel-title">Admission funnel</div>
                        <div class="truncate text-[12px] text-muted">{{ $cycle->title }}</div>
                    </div>
                    <x-ui.status :value="$cycle->status" />
                </div>
                <div class="space-y-3 p-5">
                    @php $top = max(1, $funnel[0]['value']); @endphp
                    @foreach ($funnel as $i => $step)
                        @php $pct = round($step['value'] / $top * 100); @endphp
                        <div>
                            <div class="mb-1 flex items-baseline justify-between text-[12.5px]">
                                <span class="font-medium text-ink-2">{{ $step['label'] }}</span>
                                <span class="flex items-baseline gap-2">
                                    <span class="numeral text-[18px]">{{ number_format($step['value']) }}</span>
                                    @if ($i > 0 && $funnel[$i - 1]['value'])<span class="mono text-[10.5px] text-muted">{{ round($step['value'] / $funnel[$i - 1]['value'] * 100) }}%</span>@endif
                                </span>
                            </div>
                            <div class="h-2.5 overflow-hidden rounded-full bg-sunken">
                                <div class="h-full rounded-full" style="width: {{ max(2, $pct) }}%; background: var(--{{ $step['tone'] }})"></div>
                            </div>
                        </div>
                    @endforeach
                </div>
                @if ($demand->isNotEmpty())
                    <div class="border-t border-line px-5 py-4">
                        <div class="eyebrow mb-3">Demand vs seats</div>
                        <div class="space-y-2">
                            @foreach ($demand->take(5) as $p)
                                @php $ratio = $p->seats ? $p->applied / $p->seats : 0; @endphp
                                <div class="flex items-center gap-3 text-[12.5px]">
                                    <span class="mono w-16 shrink-0 text-ink-2">{{ $p->code }}</span>
                                    <div class="relative h-2 flex-1 rounded-full bg-sunken">
                                        <div class="absolute inset-y-0 left-0 rounded-full {{ $ratio >= 1 ? 'bg-brass' : 'bg-info' }}" style="width: {{ min(100, $ratio * 100) }}%"></div>
                                    </div>
                                    <span class="mono w-20 shrink-0 text-right text-muted">{{ $p->applied }}/{{ $p->seats }}</span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
            </section>

            <section class="panel flex flex-col xl:col-span-7">
                <div class="panel-head">
                    <div class="flex items-center gap-3">
                        <span class="grid h-8 w-8 place-items-center rounded-lg bg-brass-soft text-brass"><x-icon name="sparkles" :size="16" /></span>
                        <div>
                            <div class="panel-title">AI document verification</div>
                            <div class="text-[12px] text-muted">Applications where the uploaded documents disagree with each other or with the form</div>
                        </div>
                    </div>
                    @if (Route::has('admissions.verification'))<a href="{{ route('admissions.verification') }}" class="btn btn-ghost btn-sm">Queue ({{ $flaggedCount }})</a>@endif
                </div>
                @if ($flagged->isEmpty())
                    <x-ui.empty icon="shield" title="No conflicts to resolve" description="Every submitted application’s documents agree with the form. New flags will appear here automatically." />
                @else
                    <ul class="divide-y divide-line">
                        @foreach ($flagged as $app)
                            <li>
                                <a href="{{ Route::has('admissions.show') ? route('admissions.show', $app) : '#' }}" class="flex items-center gap-4 px-5 py-3.5 hover:bg-sunken/50">
                                    <div class="relative grid h-11 w-11 shrink-0 place-items-center">
                                        <x-ui.ring :value="$app->verification_score ?? 0" :size="44" :stroke="4" :tone="$app->verification_status === 'mismatch' ? 'bad' : 'warn'" />
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <div class="flex items-center gap-2">
                                            <span class="truncate text-[13.5px] font-semibold text-ink">{{ $app->name ?? 'Unnamed applicant' }}</span>
                                            <span class="mono text-[11px] text-muted">{{ $app->tracking_no }}</span>
                                        </div>
                                        <div class="mt-1 flex flex-wrap gap-1.5">
                                            @forelse ($app->checks->take(3) as $check)
                                                <span class="badge {{ $check->result === 'mismatch' ? 'badge-bad' : 'badge-warn' }} badge-plain">{{ $check->label }}</span>
                                            @empty
                                                <span class="text-[12px] text-muted">Low reading confidence</span>
                                            @endforelse
                                        </div>
                                    </div>
                                    <span class="mono hidden text-[11.5px] text-muted sm:block">{{ $app->program?->code }}</span>
                                    <x-icon name="chevron-right" :size="16" class="text-muted" />
                                </a>
                            </li>
                        @endforeach
                    </ul>
                @endif
                @if ($isAdmissions)
                    <div class="mt-auto border-t border-line p-4">
                        <div class="eyebrow mb-2">Submissions, last 20 days</div>
                        <div class="h-[120px]">
                            <canvas x-data="chart({ type: 'bar', labels: @js($daily->keys()), series: [{ label: 'Applications', data: @js($daily->values()), tone: 'brass', thickness: 14 }] })"></canvas>
                        </div>
                    </div>
                @endif
            </section>
        @endif

        {{-- ================= By program / defaulters ================= --}}
        @unless ($isAdmissions)
            <section class="panel {{ $leadership ? 'xl:col-span-4' : 'xl:col-span-6' }}">
                <div class="panel-head">
                    <div>
                        <div class="panel-title">By program · 30 days</div>
                        <div class="text-[12px] text-muted">Share of lectures attended</div>
                    </div>
                </div>
                <div class="space-y-2.5 p-5">
                    @forelse ($byProgram as $row)
                        <div class="flex items-center gap-3 text-[12.5px]">
                            <span class="mono w-16 shrink-0 text-ink-2">{{ $row['code'] }}</span>
                            <div class="relative h-6 flex-1 overflow-hidden rounded-md bg-sunken">
                                <div class="absolute inset-y-0 left-0" style="width: {{ $row['rate'] }}%; background: color-mix(in oklab, var(--{{ $toneFor($row['rate']) }}) 75%, transparent)"></div>
                                <div class="absolute inset-y-0 border-l-2 border-dashed border-brass" style="left: {{ $threshold }}%"></div>
                            </div>
                            <span class="numeral w-12 shrink-0 text-right text-[15px]">{{ $row['rate'] }}</span>
                        </div>
                    @empty
                        <x-ui.empty icon="chart" title="No data yet" description="Program comparisons appear after the first week of registers." />
                    @endforelse
                </div>
            </section>

            <section class="panel flex flex-col {{ $leadership ? 'xl:col-span-4' : 'xl:col-span-6' }}">
                <div class="panel-head">
                    <div>
                        <div class="panel-title">Lowest attendance</div>
                        <div class="text-[12px] text-muted">{{ $defaulterCount }} below {{ $threshold }}% this term</div>
                    </div>
                    @if (Route::has('attendance.defaulters'))<a href="{{ route('attendance.defaulters') }}" class="btn btn-ghost btn-sm">All</a>@endif
                </div>
                @if ($defaulters->isEmpty())
                    <x-ui.empty icon="shield" title="Nobody below {{ $threshold }}%" description="Students who slip under the attendance rule will be listed here with one-tap guardian alerts." />
                @else
                    <ul class="divide-y divide-line">
                        @foreach ($defaulters as $row)
                            <li class="flex items-center gap-3 px-5 py-2.5">
                                <x-ui.avatar :name="$row['student']->name" :size="30" />
                                <div class="min-w-0 flex-1">
                                    <a href="{{ Route::has('attendance.student') ? route('attendance.student', $row['student']) : '#' }}" class="block truncate text-[13px] font-semibold text-ink hover:underline">{{ $row['student']->name }}</a>
                                    <div class="mono truncate text-[11px] text-muted">{{ $row['student']->roll_no }}</div>
                                </div>
                                <div class="w-24">
                                    <div class="mb-1 text-right"><span class="numeral text-[15px] {{ $row['rate'] < 60 ? 'text-bad' : 'text-warn' }}">{{ $row['rate'] }}%</span></div>
                                    <div class="h-1 rounded-full bg-sunken"><div class="h-1 rounded-full {{ $row['rate'] < 60 ? 'bg-bad' : 'bg-warn' }}" style="width: {{ $row['rate'] }}%"></div></div>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>
        @endunless

        {{-- ================= Website ================= --}}
        @if ($leadership)
            <section class="panel flex flex-col xl:col-span-4" data-area="studio">
                <div class="panel-head">
                    <div class="flex items-center gap-3">
                        <span class="grid h-8 w-8 place-items-center rounded-lg bg-info-soft text-info"><x-icon name="globe" :size="16" /></span>
                        <div>
                            <div class="panel-title">On the website now</div>
                            <div class="text-[12px] text-muted">{{ $cms['live'] }} live · {{ $cms['scheduled'] }} scheduled</div>
                        </div>
                    </div>
                    @if (Route::has('studio.dashboard'))<a href="{{ route('studio.dashboard') }}" class="btn btn-ghost btn-sm">Studio</a>@endif
                </div>
                @if ($cms['latest']->isEmpty() && $cms['events']->isEmpty())
                    <x-ui.empty icon="megaphone" title="Nothing published yet" description="Announcements and notices you publish in the studio appear on the college website and here.">
                        @if (Route::has('studio.posts.create'))<a href="{{ route('studio.posts.create') }}" class="btn btn-primary btn-sm">Write the first one</a>@endif
                    </x-ui.empty>
                @else
                    <ul class="divide-y divide-line">
                        @foreach ($cms['events'] as $event)
                            <li class="flex gap-3 px-5 py-3">
                                <div class="w-11 shrink-0 overflow-hidden rounded-lg border border-line text-center">
                                    <div class="bg-brass py-0.5 text-[9.5px] font-bold uppercase tracking-wider text-[#1b1405]">{{ $event->event_starts_at->format('M') }}</div>
                                    <div class="numeral py-1 text-[18px]">{{ $event->event_starts_at->format('j') }}</div>
                                </div>
                                <div class="min-w-0">
                                    <div class="truncate text-[13px] font-semibold text-ink">{{ $event->title }}</div>
                                    <div class="truncate text-[12px] text-muted">{{ $event->venue ?? 'Venue to be announced' }} · {{ $event->event_starts_at->format('g:i A') }}</div>
                                </div>
                            </li>
                        @endforeach
                        @foreach ($cms['latest'] as $post)
                            <li class="px-5 py-3">
                                <div class="flex items-center gap-2">
                                    <x-ui.status :value="$post->type === 'notice' ? 'submitted' : 'live'" :label="ucfirst($post->type)" />
                                    @if ($post->is_pinned)<x-icon name="pin" :size="13" class="text-brass" />@endif
                                    <span class="ml-auto text-[11px] text-muted">{{ $post->published_at->diffForHumans(short: true) }}</span>
                                </div>
                                <div class="mt-1 line-clamp-1 text-[13px] font-medium text-ink">{{ $post->title }}</div>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>

            {{-- ================= Enrolment ================= --}}
            <section class="panel xl:col-span-7">
                <div class="panel-head">
                    <div>
                        <div class="panel-title">Enrolment against sanctioned seats</div>
                        <div class="text-[12px] text-muted">Active students per program, all running sessions</div>
                    </div>
                    @if (Route::has('academics.programs.index'))<a href="{{ route('academics.programs.index') }}" class="btn btn-ghost btn-sm">Programs</a>@endif
                </div>
                <div class="overflow-x-auto">
                    <table class="table">
                        <thead><tr><th>Program</th><th>Level</th><th class="num">Enrolled</th><th class="num">Capacity</th><th class="w-[34%]">Fill</th></tr></thead>
                        <tbody>
                        @foreach ($enrolment as $p)
                            @php $capacity = $p->seats * max(1, $p->batches); $fill = $capacity ? $p->enrolled / $capacity * 100 : 0; @endphp
                            <tr>
                                <td><div class="font-semibold text-ink">{{ $p->name }}</div><div class="mono text-[11px] text-muted">{{ $p->code }}</div></td>
                                <td class="text-muted">{{ $p->levelLabel() }}</td>
                                <td class="num numeral text-[16px] text-ink">{{ $p->enrolled }}</td>
                                <td class="num mono text-muted" title="{{ $p->seats }} seats × {{ max(1, $p->batches) }} running batch(es)">{{ $capacity }}</td>
                                <td>
                                    <div class="flex items-center gap-2"><div class="h-2 flex-1 rounded-full bg-sunken"><div class="h-2 rounded-full bg-brand" style="width: {{ min(100, round($fill)) }}%"></div></div><span class="mono w-9 text-right text-[11px] text-muted">{{ round($fill) }}%</span></div>
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </section>

            {{-- ================= Activity ================= --}}
            <section class="panel xl:col-span-5">
                <div class="panel-head">
                    <div class="panel-title">Recent activity</div>
                    @if (Route::has('system.activity'))<a href="{{ route('system.activity') }}" class="btn btn-ghost btn-sm">Audit log</a>@endif
                </div>
                @if ($activity->isEmpty())
                    <x-ui.empty icon="list" title="No activity recorded yet" description="Every sign-in, verification and publication is written to the audit log." />
                @else
                    <ol class="px-5 py-3">
                        @foreach ($activity as $log)
                            <li class="flex gap-3 py-2">
                                <x-ui.avatar :name="$log->user?->name ?? 'System'" :size="26" />
                                <div class="min-w-0 flex-1 text-[12.5px] leading-snug">
                                    <span class="font-semibold text-ink">{{ $log->user?->name ?? 'System' }}</span>
                                    <span class="text-ink-2">{{ lcfirst($log->description) }}</span>
                                    <div class="mono mt-0.5 text-[10.5px] text-muted">{{ $log->created_at->diffForHumans() }} · {{ $log->action }}</div>
                                </div>
                            </li>
                        @endforeach
                    </ol>
                @endif
            </section>
        @endif
    </div>
</x-layouts.app>
