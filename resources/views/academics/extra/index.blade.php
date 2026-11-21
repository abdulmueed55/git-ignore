<x-layouts.app title="Extra classes">
    <x-ui.page-header eyebrow="Academics" title="Extra classes"
        description="Extra, make-up and test classes outside the weekly timetable. The teacher is alerted before each one, it shows on their day view for marking attendance, and students can be told by SMS.">
        <x-slot:actions>
            <a href="{{ route('academics.timetable') }}" class="btn btn-ghost"><x-icon name="clock" :size="15" /> Weekly timetable</a>
        </x-slot:actions>
    </x-ui.page-header>

    <div class="grid gap-6 xl:grid-cols-[1fr_380px]">
        <div class="min-w-0 space-y-6">
            @unless ($leader)
                {{-- This week: regular periods + extra classes --}}
                <section class="panel">
                    <div class="panel-head">
                        <div class="panel-title">My week</div>
                        <span class="eyebrow">alerts {{ config('timetable.reminder_minutes') }} min before each class</span>
                    </div>
                    <div class="grid gap-px bg-line sm:grid-cols-2 lg:grid-cols-3">
                        @foreach ($week as $day)
                            <div class="min-w-0 bg-surface p-4">
                                <div class="mb-2 flex items-baseline justify-between gap-2">
                                    <div class="text-[13.5px] font-bold text-ink">{{ $day['date']->isToday() ? 'Today' : $day['date']->format('l') }}</div>
                                    <div class="mono text-[11.5px] text-muted">{{ $day['date']->format('j M') }}</div>
                                </div>
                                @php $holiday = config('attendance.holidays')[$day['date']->toDateString()] ?? null; @endphp
                                @if ($holiday)
                                    <div class="text-[12.5px] text-warn">Holiday — {{ $holiday }}</div>
                                @elseif ($day['items']->isEmpty())
                                    <div class="text-[12.5px] text-muted">No classes</div>
                                @else
                                    <ul class="space-y-1.5">
                                        @foreach ($day['items'] as $i)
                                            <li class="flex items-start gap-2 text-[12.5px] {{ $i->end->isPast() ? 'opacity-55' : '' }}">
                                                <span class="mono w-11 shrink-0 font-semibold text-ink">{{ $i->start->format('H:i') }}</span>
                                                <span class="min-w-0 flex-1 leading-snug">
                                                    <span class="font-semibold text-ink">{{ $i->course->subject->name }}</span>
                                                    @if ($i->type === 'extra')<span class="badge badge-brass !h-[17px] !text-[10px]">{{ $i->label }}</span>@endif
                                                    <span class="block truncate text-muted">{{ $i->course->section->label() }} · {{ $i->room }}</span>
                                                </span>
                                            </li>
                                        @endforeach
                                    </ul>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </section>
            @endunless

            <section class="panel overflow-hidden">
                <div class="panel-head">
                    <div class="panel-title">Upcoming extra classes</div>
                    <span class="mono text-[11px] text-muted">{{ $upcoming->count() }}</span>
                </div>
                @if ($upcoming->isEmpty())
                    <x-ui.empty icon="calendar" title="No extra classes scheduled"
                        description="Schedule an extra, make-up or test class with the form — the teacher gets an alert before it starts and it appears on the day view for attendance.">
                        <a href="#schedule" class="btn btn-primary"><x-icon name="plus" :size="15" /> Schedule a class</a>
                    </x-ui.empty>
                @else
                    <ul class="divide-y divide-line">
                        @foreach ($upcoming as $e)
                            <li class="flex flex-wrap items-center gap-x-4 gap-y-2 px-4 py-3">
                                <div class="w-14 shrink-0 text-center leading-tight">
                                    <div class="numeral text-[20px]">{{ $e->held_on->format('j') }}</div>
                                    <div class="eyebrow !text-[9.5px]">{{ $e->held_on->format('M · D') }}</div>
                                </div>
                                <div class="min-w-0 flex-1">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span class="text-[14px] font-semibold text-ink">{{ $e->course->subject->name }}</span>
                                        <span class="badge badge-brass">{{ $e->kindLabel() }}</span>
                                        @if ($e->held_on->isToday())<span class="badge badge-info">Today</span>@endif
                                        @if ($e->students_notified)<span class="badge badge-ok">Students told by SMS</span>@endif
                                    </div>
                                    <div class="mt-0.5 text-[12.5px] text-muted">
                                        <span class="mono">{{ substr($e->starts_at, 0, 5) }}–{{ substr($e->ends_at, 0, 5) }}</span> · Room {{ $e->room }} · {{ $e->course->section->label() }}
                                        @if ($leader) · {{ $e->course->teacher?->name ?? 'No teacher' }} @endif
                                    </div>
                                    @if ($e->reason)<div class="mt-0.5 text-[12px] text-ink-2">{{ $e->reason }}</div>@endif
                                </div>
                                <div class="flex shrink-0 items-center gap-2">
                                    @if ($e->held_on->isToday())
                                        <a href="{{ route('attendance.mark', ['course' => $e->course_id, 'date' => $e->held_on->toDateString(), 'time' => substr($e->starts_at, 0, 5), 'extra' => $e->id]) }}" class="btn btn-primary btn-sm"><x-icon name="check" :size="14" /> Register</a>
                                    @endif
                                    <form method="POST" action="{{ route('academics.extra.cancel', $e) }}" data-confirm="Cancel this {{ strtolower($e->kindLabel()) }}?{{ $e->students_notified ? ' Students will get a cancellation SMS.' : '' }}">
                                        @csrf @method('PATCH')
                                        <button class="btn btn-ghost btn-sm">Cancel</button>
                                    </form>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>

            @if ($recent->isNotEmpty())
                <section class="panel overflow-hidden">
                    <div class="panel-head"><div class="panel-title">Past and cancelled</div><span class="eyebrow">last 30 days</span></div>
                    <ul class="divide-y divide-line">
                        @foreach ($recent as $e)
                            <li class="flex flex-wrap items-center gap-x-3 gap-y-1 px-4 py-2.5 text-[12.5px]">
                                <span class="mono w-24 shrink-0 text-muted">{{ $e->held_on->format('D j M') }}</span>
                                <span class="min-w-0 flex-1 truncate"><span class="font-semibold text-ink">{{ $e->course->subject->name }}</span> · {{ $e->course->section->label() }} · {{ substr($e->starts_at, 0, 5) }}</span>
                                <x-ui.status :value="$e->cancelled_at ? 'cancelled' : 'completed'" />
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endif
        </div>

        {{-- Schedule form --}}
        <form id="schedule" method="POST" action="{{ route('academics.extra.store') }}" class="panel self-start p-5">
            @csrf
            <div class="panel-title">Schedule a class</div>
            <p class="mb-4 mt-1 text-[12.5px] text-muted">Checked against the timetable: neither the teacher nor the class can be double-booked.</p>

            @if ($courses->isEmpty())
                <x-ui.empty icon="book" title="No subjects assigned yet" description="Ask the principal’s office to assign you a subject in the timetable, then schedule extra classes here.">
                    <a href="{{ route('academics.timetable') }}" class="btn btn-primary">Open timetable</a>
                </x-ui.empty>
            @else
                <div class="space-y-4">
                    <div>
                        <label for="f_course_id" class="field-label">Subject &amp; class <span class="req">*</span></label>
                        <select id="f_course_id" name="course_id" class="input" required @error('course_id') aria-invalid="true" @enderror>
                            <option value="">Select…</option>
                            @foreach ($courses as $group => $list)
                                <optgroup label="{{ $group }}">
                                    @foreach ($list as $c)
                                        <option value="{{ $c->id }}" @selected(old('course_id') == $c->id)>{{ $c->subject->name }}{{ $leader ? ' — '.($c->teacher?->name ?? 'no teacher') : '' }}</option>
                                    @endforeach
                                </optgroup>
                            @endforeach
                        </select>
                        @error('course_id')<div class="field-error">{{ $message }}</div>@enderror
                    </div>
                    <x-ui.field name="kind" type="select" label="Type" required :options="$kinds" :value="old('kind', 'extra')" :empty="false" />
                    <x-ui.field name="held_on" type="date" label="Date" required :value="old('held_on', today()->toDateString())" :min="today()->toDateString()" />
                    <div class="grid grid-cols-2 gap-3">
                        <x-ui.field name="starts_at" type="time" label="Starts" required :value="old('starts_at', '13:45')" />
                        <x-ui.field name="ends_at" type="time" label="Ends" required :value="old('ends_at', '14:30')" />
                    </div>
                    <x-ui.field name="room" label="Room" required maxlength="32" :value="old('room')" placeholder="e.g. B-2 or Physics Lab" />
                    <x-ui.field name="reason" label="Note for students" maxlength="160" :value="old('reason')" placeholder="e.g. Revision before December test" />
                    <label class="flex items-start gap-2.5 rounded-md border border-line p-3 text-[12.5px]">
                        <input type="checkbox" name="notify_students" value="1" class="mt-0.5" @checked(old('notify_students', true))>
                        <span><span class="font-semibold text-ink">Tell the students by SMS</span><span class="block text-muted">Queued in Guardian alerts; the principal’s office sends the outbox.</span></span>
                    </label>
                    <button class="btn btn-primary w-full"><x-icon name="plus" :size="15" /> Schedule class</button>
                </div>
            @endif
        </form>
    </div>
</x-layouts.app>
