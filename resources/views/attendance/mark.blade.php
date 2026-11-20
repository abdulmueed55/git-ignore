<x-layouts.app :title="'Register · '.$course->subject->name">
    @php
        $threshold = config('attendance.threshold');
        $margin = config('attendance.warning_margin');
        $chip = [
            'P' => 'peer-checked:!border-ok peer-checked:!bg-ok peer-checked:!text-paper',
            'A' => 'peer-checked:!border-bad peer-checked:!bg-bad peer-checked:!text-paper',
            'L' => 'peer-checked:!border-warn peer-checked:!bg-warn peer-checked:!text-paper',
            'E' => 'peer-checked:!border-info peer-checked:!bg-info peer-checked:!text-paper',
        ];
    @endphp

    <x-ui.page-header :eyebrow="'Register · '.$date->format('l, j F Y')" :title="$course->subject->name"
        :description="$course->section->label().' · '.($course->teacher?->name ?? 'No teacher assigned').' · '.$rows->count().' students on roll'">
        <x-slot:actions>
            <a href="{{ route('attendance.index', ['date' => $date->toDateString()]) }}" class="btn btn-ghost"><x-icon name="arrow-left" :size="15" /> Day view</a>
            <a href="{{ route('attendance.register', ['section_id' => $course->section_id, 'course_id' => $course->id, 'month' => $date->format('Y-m')]) }}" class="btn btn-ghost"><x-icon name="printer" :size="15" /> Monthly sheet</a>
        </x-slot:actions>
    </x-ui.page-header>

    @if ($errors->any())
        <div class="mb-5 rounded-xl border border-bad/30 bg-bad-soft px-4 py-3 text-[13px] text-bad">
            <div class="font-semibold">The register was not saved.</div>
            <ul class="mt-1 list-disc pl-5">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
        </div>
    @endif

    @if ($locked)
        <div class="mb-5 flex items-center gap-3 rounded-xl border border-line-strong bg-sunken px-4 py-3 text-[13px]">
            <x-icon name="lock" class="text-muted" />
            <span>Locked since <strong>{{ $lecture->locked_at->format('j M, H:i') }}</strong>.
                {{ $readonly ? 'Only the principal’s office can change it now.' : 'You can still edit it because you are leadership — changes are logged.' }}</span>
        </div>
    @endif

    <form method="POST" action="{{ route('attendance.save', $course) }}" x-data="register(@js($rows->map(fn ($r) => ['s' => $r['s'], 'name' => strtolower($r['name'].' '.$r['roll'])])))"
          @keydown.window="key($event)" class="grid gap-6 pb-20 lg:grid-cols-[1fr_320px] lg:pb-0">
        @csrf
        <input type="hidden" name="date" value="{{ $date->toDateString() }}">
        @if ($slot)<input type="hidden" name="slot" value="{{ $slot->id }}">@endif

        <fieldset @disabled($readonly) class="min-w-0">
            {{-- Period switcher --}}
            <div class="mb-4 flex flex-wrap items-center gap-2">
                @if ($slots->count() > 1)
                    <div class="tabs" role="tablist">
                        @foreach ($slots as $s)
                            <a role="tab" aria-selected="{{ $slot?->id === $s->id ? 'true' : 'false' }}" class="tab mono" href="{{ route('attendance.mark', ['course' => $course->id, 'date' => $date->toDateString(), 'slot' => $s->id]) }}">{{ substr($s->starts_at, 0, 5) }}</a>
                        @endforeach
                    </div>
                @elseif ($slot)
                    <span class="badge badge-plain mono">{{ substr($slot->starts_at, 0, 5) }}–{{ substr($slot->ends_at, 0, 5) }} · {{ $slot->room ?? $course->section->room }}</span>
                @else
                    <div class="flex items-center gap-2 rounded-xl border border-dashed border-brass/50 bg-brass-soft/40 px-3 py-2 text-[12.5px]">
                        <x-icon name="info" :size="15" class="text-brass" />
                        <span>No timetabled period for this subject on {{ $date->format('l') }} — recording an <strong>extra / make-up lecture</strong> at</span>
                        <input type="time" name="time" value="{{ old('time', $time) }}" required class="input !h-8 !w-[110px]">
                    </div>
                @endif
                <span class="badge {{ $lecture ? 'badge-ok' : 'badge-warn' }}">{{ $lecture ? 'Marked by '.($lecture->marker?->name ?? 'staff').' · '.$lecture->updated_at->diffForHumans() : 'Not marked yet' }}</span>
            </div>

            <div class="panel overflow-hidden">
                <div class="panel-head flex-wrap">
                    <div class="relative min-w-[200px] flex-1">
                        <x-icon name="search" :size="15" class="absolute left-3 top-1/2 -translate-y-1/2 text-muted" />
                        <input type="search" x-model="q" placeholder="Find by name or roll no…" class="input !h-9 pl-9" aria-label="Search students">
                    </div>
                    <div class="flex items-center gap-1.5">
                        <button type="button" class="btn btn-ghost btn-sm" @click="all('P')"><span class="h-2 w-2 rounded-full bg-ok"></span> All present</button>
                        <button type="button" class="btn btn-ghost btn-sm" @click="all('A')"><span class="h-2 w-2 rounded-full bg-bad"></span> All absent</button>
                    </div>
                </div>
                <div class="hidden items-center gap-4 border-b border-line bg-sunken/50 px-4 py-2 text-[11.5px] text-muted md:flex">
                    <span>Keyboard:</span>
                    <span><kbd class="mono rounded border border-line bg-surface px-1">↑</kbd> <kbd class="mono rounded border border-line bg-surface px-1">↓</kbd> move</span>
                    <span><kbd class="mono rounded border border-line bg-surface px-1">P</kbd> <kbd class="mono rounded border border-line bg-surface px-1">A</kbd> <kbd class="mono rounded border border-line bg-surface px-1">L</kbd> <kbd class="mono rounded border border-line bg-surface px-1">E</kbd> mark &amp; next</span>
                    <span><kbd class="mono rounded border border-line bg-surface px-1">Space</kbd> cycle</span>
                    <span class="ml-auto">% = this subject so far · <span class="text-bad">red</span> below {{ $threshold }}%</span>
                </div>

                @if ($rows->isEmpty())
                    <x-ui.empty icon="users" title="No active students in this class" description="Students appear here once they are enrolled into {{ $course->section->label() }}.">
                        @if (Route::has('students.index'))<a href="{{ route('students.index') }}" class="btn btn-primary">Manage students</a>@endif
                    </x-ui.empty>
                @endif

                <ol class="divide-y divide-line">
                    @foreach ($rows as $i => $r)
                        @php
                            $rate = $r['rate'];
                            $rateCls = $rate === null ? 'text-muted' : ($rate < $threshold ? 'text-bad' : ($rate < $threshold + $margin ? 'text-warn' : 'text-ok'));
                        @endphp
                        <li class="flex flex-wrap items-center gap-x-3 gap-y-2 px-3 py-2.5 transition sm:px-4"
                            x-show="match({{ $i }})" :class="cur === {{ $i }} && 'bg-sunken/70'" @click="cur = {{ $i }}"
                            x-data="{ note: {{ $r['remarks'] ? 'true' : 'false' }} }">
                            <span class="mono w-6 shrink-0 text-right text-[11px] text-muted">{{ $i + 1 }}</span>
                            <x-ui.avatar :name="$r['name']" :size="32" class="hidden sm:inline-grid" />
                            <div class="min-w-0 flex-1 leading-tight">
                                <div class="flex items-center gap-2">
                                    <span class="truncate text-[13.5px] font-semibold text-ink">{{ $r['name'] }}</span>
                                    @if ($r['leave'])<span class="badge badge-info !h-[18px] !text-[10px]">On leave</span>@endif
                                </div>
                                <div class="mt-0.5 flex items-center gap-2 text-[11.5px] text-muted">
                                    <span class="mono">{{ $r['roll'] }}</span>
                                    <span class="hidden truncate sm:inline">s/o {{ $r['father'] }}</span>
                                </div>
                            </div>
                            <div class="w-14 shrink-0 text-right" title="Overall {{ $r['overall'] ?? '—' }}%">
                                <div class="mono text-[12.5px] font-semibold {{ $rateCls }}">{{ $rate !== null ? round($rate).'%' : 'new' }}</div>
                                <div class="text-[10px] text-muted">{{ $rate !== null && $rate < $threshold ? 'short' : 'subject' }}</div>
                            </div>
                            <div class="flex shrink-0 items-center gap-1.5" role="radiogroup" aria-label="Status for {{ $r['name'] }}">
                                @foreach (['P', 'A', 'L', 'E'] as $st)
                                    <label class="cursor-pointer" title="{{ config('attendance.statuses')[$st] }}">
                                        <input type="radio" class="peer sr-only" name="status[{{ $r['id'] }}]" value="{{ $st }}" x-model="rows[{{ $i }}].s" @checked(old('status.'.$r['id'], $r['s']) === $st)>
                                        <span class="att-chip {{ $chip[$st] }} peer-focus-visible:ring-2 peer-focus-visible:ring-brass">{{ $st }}</span>
                                    </label>
                                @endforeach
                                <button type="button" class="ml-1 grid h-8 w-8 place-items-center rounded-md text-muted hover:bg-sunken hover:text-ink" @click.stop="note = !note" title="Add remark"><x-icon name="edit" :size="14" /></button>
                            </div>
                            <div class="basis-full pl-9 sm:pl-[84px]" x-show="note" x-cloak>
                                <input type="text" name="remarks[{{ $r['id'] }}]" value="{{ old('remarks.'.$r['id'], $r['remarks']) }}" maxlength="120" class="input !h-8 text-[12.5px]" placeholder="Remark (e.g. came after roll call, medical)">
                            </div>
                        </li>
                    @endforeach
                </ol>
            </div>
        </fieldset>

        {{-- Mobile: always-visible save bar --}}
        @unless ($readonly || $rows->isEmpty())
            <div class="fixed inset-x-0 bottom-0 z-20 flex items-center gap-3 border-t border-line bg-surface/95 px-4 py-3 backdrop-blur lg:hidden">
                <div class="mono flex gap-3 text-[13px] font-semibold">
                    <span class="text-ok">P <span x-text="counts.P + counts.L"></span></span>
                    <span class="text-bad">A <span x-text="counts.A"></span></span>
                    <span class="text-info">E <span x-text="counts.E"></span></span>
                </div>
                <button class="btn btn-primary ml-auto" @click="if (!document.getElementById('f_topic').value) { $event.preventDefault(); document.getElementById('f_topic').focus(); document.getElementById('f_topic').scrollIntoView({block:'center'}); }">
                    <x-icon name="check" :size="15" /> Save
                </button>
            </div>
        @endunless

        {{-- Summary + save --}}
        <aside class="space-y-5 lg:sticky lg:top-[84px] lg:self-start">
            <div class="panel p-5">
                <div class="flex items-center gap-5">
                    <div class="relative grid h-[104px] w-[104px] shrink-0 place-items-center">
                        <svg width="104" height="104" class="-rotate-90">
                            <circle cx="52" cy="52" r="46" fill="none" stroke="var(--sunken)" stroke-width="9"/>
                            <circle cx="52" cy="52" r="46" fill="none" :stroke="rate >= {{ $threshold }} ? 'var(--ok)' : 'var(--bad)'" stroke-width="9" stroke-linecap="round"
                                    stroke-dasharray="289.03" :stroke-dashoffset="289.03 * (1 - rate / 100)" style="transition: stroke-dashoffset .3s ease"/>
                        </svg>
                        <div class="absolute text-center">
                            <div class="numeral text-[26px]"><span x-text="Math.round(rate)">{{ $rows->isEmpty() ? 0 : 100 }}</span><span class="text-[13px] text-muted">%</span></div>
                            <div class="eyebrow !text-[9px]">present</div>
                        </div>
                    </div>
                    <dl class="grid flex-1 grid-cols-2 gap-x-3 gap-y-2 text-[12px]">
                        <div><dt class="text-muted">Present</dt><dd class="mono text-[17px] font-semibold text-ok" x-text="counts.P">{{ $rows->where('s', 'P')->count() }}</dd></div>
                        <div><dt class="text-muted">Absent</dt><dd class="mono text-[17px] font-semibold text-bad" x-text="counts.A">{{ $rows->where('s', 'A')->count() }}</dd></div>
                        <div><dt class="text-muted">Late</dt><dd class="mono text-[17px] font-semibold text-warn" x-text="counts.L">{{ $rows->where('s', 'L')->count() }}</dd></div>
                        <div><dt class="text-muted">Leave</dt><dd class="mono text-[17px] font-semibold text-info" x-text="counts.E">{{ $rows->where('s', 'E')->count() }}</dd></div>
                    </dl>
                </div>
                <div class="mt-5 border-t border-line pt-4">
                    <x-ui.field name="topic" label="Topic covered" :value="$lecture?->topic" required maxlength="200" placeholder="e.g. Projectile motion — numericals" :disabled="$readonly" />
                </div>
                <button class="btn btn-primary mt-4 h-11 w-full text-[14px]" @disabled($readonly || $rows->isEmpty())>
                    <x-icon name="check" :size="16" /> {{ $lecture ? 'Update register' : 'Save register' }}
                </button>
                <p class="mt-2 text-center text-[11.5px] text-muted">
                    Guardians of absent students get an SMS alert.
                    @if (! $lecture) Editable for {{ config('attendance.lock_after_hours') }} h after the period. @endif
                </p>
            </div>

            <div class="panel">
                <div class="panel-head"><div class="panel-title">Recent registers</div><span class="eyebrow">this subject</span></div>
                <ul class="divide-y divide-line">
                    @forelse ($recent as $l)
                        @php $lr = $l->attendanceRate(); @endphp
                        <li>
                            <a href="{{ route('attendance.mark', ['course' => $course->id, 'date' => $l->held_on->toDateString(), 'slot' => $l->timetable_slot_id, 'time' => substr($l->starts_at, 0, 5)]) }}" class="flex items-center gap-3 px-4 py-2.5 hover:bg-sunken/50 {{ $lecture?->id === $l->id ? 'bg-sunken/70' : '' }}">
                                <div class="w-12 text-center leading-tight">
                                    <div class="numeral text-[18px]">{{ $l->held_on->format('j') }}</div>
                                    <div class="eyebrow !text-[9px]">{{ $l->held_on->format('M') }}</div>
                                </div>
                                <div class="min-w-0 flex-1">
                                    <div class="truncate text-[12.5px] text-ink-2">{{ $l->topic }}</div>
                                    <div class="mono text-[11px] text-muted">{{ $l->held_on->format('D') }} {{ substr($l->starts_at, 0, 5) }} · A {{ $l->absent_count }}</div>
                                </div>
                                <span class="mono text-[12px] font-semibold {{ $lr !== null && $lr < $threshold ? 'text-bad' : 'text-ok' }}">{{ $lr ?? '—' }}%</span>
                            </a>
                        </li>
                    @empty
                        <li class="px-4 py-6 text-center text-[12.5px] text-muted">This is the first register for this subject.</li>
                    @endforelse
                </ul>
            </div>
        </aside>
    </form>

    @push('scripts')
    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('register', (rows) => ({
                rows, q: '', cur: 0,
                get counts() {
                    const c = { P: 0, A: 0, L: 0, E: 0 };
                    this.rows.forEach((r) => c[r.s]++);
                    return c;
                },
                get rate() {
                    const c = this.counts, d = c.P + c.L + c.A;
                    return d ? (c.P + c.L) / d * 100 : 0;
                },
                match(i) { return !this.q || this.rows[i].name.includes(this.q.toLowerCase().trim()); },
                all(s) { this.rows.forEach((r) => { if (r.s !== 'E') r.s = s; }); },
                visible() { return this.rows.map((r, i) => i).filter((i) => this.match(i)); },
                move(d) {
                    const v = this.visible(); if (!v.length) return;
                    const at = Math.max(0, v.indexOf(this.cur));
                    this.cur = v[Math.min(v.length - 1, Math.max(0, at + d))];
                    this.$root.querySelectorAll('ol > li')[this.cur]?.scrollIntoView({ block: 'nearest' });
                },
                key(e) {
                    if (['INPUT', 'TEXTAREA', 'SELECT'].includes(e.target.tagName) && e.target.type !== 'radio') return;
                    if (e.ctrlKey || e.metaKey || e.altKey) return;
                    const k = e.key.toUpperCase();
                    if (e.key === 'ArrowDown' || k === 'J') { this.move(1); e.preventDefault(); }
                    else if (e.key === 'ArrowUp' || k === 'K') { this.move(-1); e.preventDefault(); }
                    else if (['P', 'A', 'L', 'E'].includes(k)) { this.rows[this.cur].s = k; this.move(1); e.preventDefault(); }
                    else if (e.key === ' ') { const o = ['P', 'A', 'L', 'E']; const r = this.rows[this.cur]; r.s = o[(o.indexOf(r.s) + 1) % 4]; e.preventDefault(); }
                },
            }));
        });
    </script>
    @endpush
</x-layouts.app>
