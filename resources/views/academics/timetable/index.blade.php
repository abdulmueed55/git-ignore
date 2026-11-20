@php
    use App\Http\Controllers\Academics\TimetableController as TT;
    $days = TT::DAYS;
    $today = now()->dayOfWeekIso;
    $editable = $leader && $section;
    $slotJson = $slots->mapWithKeys(fn ($s) => [$s->id => [
        'id' => $s->id, 'course_id' => $s->course_id, 'weekday' => $s->weekday,
        'starts_at' => substr($s->starts_at, 0, 5), 'ends_at' => substr($s->ends_at, 0, 5), 'room' => $s->room,
        'label' => $s->course->subject->name,
    ]]);
    $defaultRoom = $section?->room ?? '';
@endphp
<x-layouts.app title="Timetable">
    <x-ui.page-header eyebrow="Academics" title="Timetable"
        :description="$section ? $section->program->name.' · '.$section->name.' · Session '.$section->academicSession?->name : 'Teaching timetable of '.$teacher?->name">
        <x-slot:actions>
            <button onclick="window.print()" class="btn btn-ghost"><x-icon name="printer" :size="15" /> Print</button>
        </x-slot:actions>
    </x-ui.page-header>

    {{-- View switcher --}}
    <div class="mb-5 flex flex-wrap items-center gap-2">
        <div class="tabs" role="tablist">
            <span class="tab" aria-selected="{{ $section ? 'true' : 'false' }}"><x-icon name="layers" :size="14" /> By section</span>
            <span class="tab" aria-selected="{{ $teacher ? 'true' : 'false' }}"><x-icon name="id" :size="14" /> By teacher</span>
        </div>
        <form method="GET" x-data class="flex flex-wrap gap-2">
            <select name="section" class="input !h-9 !w-auto min-w-[220px]" @change="$el.form.submit()">
                <option value="">Choose section…</option>
                @foreach ($sectionOptions as $id => $label)<option value="{{ $id }}" @selected($section?->id === $id)>{{ $label }}</option>@endforeach
            </select>
        </form>
        <form method="GET" x-data>
            <select name="teacher" class="input !h-9 !w-auto min-w-[220px]" @change="$el.form.submit()">
                <option value="">Choose teacher…</option>
                @foreach ($teacherOptions as $id => $name)<option value="{{ $id }}" @selected($teacher?->id === $id)>{{ $name }}</option>@endforeach
            </select>
        </form>
        @if ($teacher)
            <span class="badge badge-brass ml-auto">{{ $myLoad }} periods / week</span>
        @endif
    </div>

    @if ($errors->any())
        <div class="mb-5 flex items-start gap-3 rounded-xl border border-bad/30 bg-bad-soft px-4 py-3 text-[13px] text-bad">
            <x-icon name="alert" :size="16" class="mt-0.5" />
            <div><strong>Timetable clash — nothing was saved.</strong>
                <ul class="mt-1 space-y-0.5">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
            </div>
        </div>
    @endif

    <div class="grid gap-6 {{ $section || $leader ? 'xl:grid-cols-[minmax(0,1fr)_300px]' : '' }}"
         x-data="{
            open: false, mode: 'add', slots: @js($slotJson),
            form: { course_id: '', weekday: 1, starts_at: '', ends_at: '', room: @js($defaultRoom) }, editId: null,
            add(day, start, end) { this.mode = 'add'; this.editId = null; this.form = { course_id: '', weekday: day, starts_at: start, ends_at: end, room: @js($defaultRoom) }; this.open = true },
            edit(id) { const s = this.slots[id]; this.mode = 'edit'; this.editId = id; this.form = { course_id: s.course_id, weekday: s.weekday, starts_at: s.starts_at, ends_at: s.ends_at, room: s.room }; this.open = true },
            action() { return this.mode === 'add' ? @js(route('academics.timetable.slots.store')) : @js(url('academics/timetable/slots')) + '/' + this.editId },
         }">
        {{-- Grid --}}
        <section class="panel h-max overflow-hidden">
            <div class="overflow-x-auto scrollbar-thin">
                <table class="w-full min-w-[720px] table-fixed border-collapse text-[12.5px]">
                    <thead>
                    <tr>
                        <th class="w-[78px] border-b border-r border-line bg-sunken/60 px-3 py-3 text-left"><span class="eyebrow">Period</span></th>
                        @foreach ($days as $d => $name)
                            <th class="border-b border-line px-3 py-3 text-left {{ $d === $today ? 'bg-brass-soft/60' : '' }}">
                                <span class="eyebrow {{ $d === $today ? '!text-brass' : '' }}">{{ $name }}</span>
                                @if ($d === $today)<span class="ml-1 text-[10px] font-semibold text-brass">· today</span>@endif
                            </th>
                        @endforeach
                    </tr>
                    </thead>
                    <tbody>
                    @foreach ($periods as $i => [$start, $end])
                        @if ($start === TT::PERIODS[3][0])
                            <tr>
                                <td class="border-b border-r border-line bg-sunken/60 px-3 py-1.5 mono text-[11px] text-muted">{{ TT::BREAK[0] }}</td>
                                <td colspan="5" class="stripe-brass border-b border-line px-3 py-1.5 text-center"><span class="eyebrow !text-brass">Break · {{ TT::BREAK[0] }}–{{ TT::BREAK[1] }}</span></td>
                            </tr>
                        @endif
                        <tr>
                            <td class="border-b border-r border-line bg-sunken/60 px-3 py-2 align-top">
                                <div class="numeral text-[17px] text-ink">{{ $i + 1 }}</div>
                                <div class="mono mt-0.5 text-[10.5px] text-muted">{{ $start }}<br>{{ $end }}</div>
                            </td>
                            @foreach ($days as $d => $name)
                                @php $cell = $grid[$d][$start] ?? []; @endphp
                                <td class="h-[84px] border-b border-line p-1.5 align-top {{ $d === $today ? 'bg-brass-soft/25' : '' }}">
                                    @forelse ($cell as $slot)
                                        @php $color = TT::deptColor($slot->course->subject->department?->code); @endphp
                                        <{{ $editable ? 'button type=button' : 'div' }} @if($editable) @click="edit({{ $slot->id }})" @endif
                                            class="group relative mb-1 block w-full overflow-hidden rounded-lg border px-2.5 py-2 text-left transition last:mb-0 {{ $editable ? 'hover:-translate-y-px hover:shadow-md' : '' }}"
                                            style="border-color: color-mix(in oklab, {{ $color }} 35%, var(--line)); background: color-mix(in oklab, {{ $color }} 11%, var(--surface));">
                                            <span class="absolute inset-y-0 left-0 w-[3px]" style="background: {{ $color }}"></span>
                                            <div class="flex items-baseline justify-between gap-2">
                                                <span class="truncate font-semibold text-ink">{{ $slot->course->subject->name }}</span>
                                                @unless ($section)<span class="mono shrink-0 text-[10.5px] text-muted">{{ $slot->room }}</span>@endunless
                                            </div>
                                            <div class="mt-1 flex items-center justify-between gap-2 text-[11px] text-muted">
                                                @if ($section)
                                                    <span class="flex min-w-0 items-center gap-1.5" title="{{ $slot->course->teacher?->name }}">
                                                        <span class="grid h-4 w-4 shrink-0 place-items-center rounded-full text-[8px] font-bold text-white" style="background: {{ $color }}">{{ $slot->course->teacher?->initials() ?? '?' }}</span>
                                                        <span class="truncate">{{ Str::of($slot->course->teacher?->name ?? 'Unassigned')->replaceMatches('/^((Prof|Dr|Mr|Ms|Mrs|Hafiz)\.?\s+)+/', '')->words(2, '') }}</span>
                                                    </span>
                                                    <span class="mono shrink-0">{{ $slot->room }}</span>
                                                @else
                                                    <span class="truncate" title="{{ $slot->course->section->label() }}">{{ $slot->course->section->program->code }} · {{ $slot->course->section->name }}</span>
                                                @endif
                                            </div>
                                        </{{ $editable ? 'button' : 'div' }}>
                                    @empty
                                        @if ($editable)
                                            <button type="button" @click="add({{ $d }}, '{{ $start }}', '{{ $end }}')"
                                                class="grid h-full min-h-[70px] w-full place-items-center rounded-lg border border-dashed border-transparent text-muted opacity-0 transition hover:border-line-strong hover:opacity-100 focus:opacity-100">
                                                <x-icon name="plus" :size="16" />
                                            </button>
                                        @else
                                            <div class="grid h-full min-h-[70px] place-items-center text-[11px] text-line-strong">—</div>
                                        @endif
                                    @endforelse
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            @if ($slots->isEmpty())
                <div class="border-t border-line">
                    <x-ui.empty icon="clock" title="Nothing scheduled yet"
                        :description="$editable ? 'Add courses on the right, then click any empty cell to schedule a period.' : ($teacher ? 'No periods are assigned to '.$teacher->name.' yet. The principal’s office schedules periods from the section timetable.' : 'Choose a section to see its timetable.')" />
                </div>
            @endif
            <div class="flex flex-wrap items-center gap-x-5 gap-y-2 border-t border-line px-5 py-3 text-[11.5px] text-muted">
                @foreach ($slots->map(fn ($s) => $s->course->subject->department)->filter()->unique('id')->sortBy('name') as $dept)
                    <span class="inline-flex items-center gap-1.5"><x-academics.dept-swatch :code="$dept->code" small /> {{ $dept->name }}</span>
                @endforeach
                @if ($editable)<span class="ml-auto">Click a period to move or remove it.</span>@endif
            </div>
        </section>

        {{-- Side panel --}}
        <aside class="space-y-6">
            @if ($section)
                <section class="panel">
                    <div class="panel-head"><div class="panel-title">Courses in this section</div><span class="mono text-[11px] text-muted">{{ $courses->count() }}</span></div>
                    @forelse ($courses as $c)
                        @php $count = $slots->where('course_id', $c->id)->count(); @endphp
                        <div class="border-b border-line px-5 py-3 last:border-0">
                            <div class="flex items-center gap-2.5">
                                <x-academics.dept-swatch :code="$c->subject->department?->code" />
                                <div class="min-w-0 flex-1">
                                    <div class="truncate text-[13px] font-medium text-ink">{{ $c->subject->name }}</div>
                                    @unless ($leader)<div class="truncate text-[12px] text-muted">{{ $c->teacher?->name }}</div>@endunless
                                </div>
                                <span class="mono text-[11px] {{ $count >= 4 ? 'text-ok' : 'text-warn' }}">{{ $count }}/wk</span>
                            </div>
                            @if ($leader)
                                <form method="POST" action="{{ route('academics.timetable.courses.teacher', $c) }}" x-data class="mt-2">
                                    @csrf @method('PUT')
                                    <select name="teacher_id" class="input !h-8 !text-[12px]" @change="$el.form.submit()" aria-label="Teacher for {{ $c->subject->name }}">
                                        @foreach ($allTeachers as $t)<option value="{{ $t->id }}" @selected($c->teacher_id === $t->id)>{{ $t->name }}</option>@endforeach
                                    </select>
                                </form>
                            @endif
                        </div>
                    @empty
                        <p class="px-5 py-4 text-[12.5px] text-muted">No subjects are taught in this section yet. Add the first one below.</p>
                    @endforelse
                </section>

                @if ($leader)
                    <section class="panel">
                        <div class="panel-head"><div class="panel-title">Add a course</div></div>
                        <form method="POST" action="{{ route('academics.timetable.courses.store') }}" class="space-y-3 p-5">
                            @csrf
                            <input type="hidden" name="section_id" value="{{ $section->id }}">
                            <x-ui.field name="subject_id" type="select" label="Subject" required
                                :options="$subjects->reject(fn ($s) => $courses->pluck('subject_id')->contains($s->id))->mapWithKeys(fn ($s) => [$s->id => $s->code.' — '.$s->name])" />
                            <x-ui.field name="teacher_id" type="select" label="Teacher" required :options="$allTeachers->pluck('name', 'id')" />
                            <button class="btn btn-primary w-full"><x-icon name="plus" :size="15" /> Add course</button>
                        </form>
                    </section>
                @endif
            @endif

            @if ($leader)
                <section class="panel">
                    <div class="panel-head"><div class="panel-title">Teacher workload</div><span class="eyebrow">periods / week</span></div>
                    <div class="max-h-[420px] space-y-2.5 overflow-y-auto p-5 scrollbar-thin">
                        @php $maxLoad = max(1, $workload->max('periods')); @endphp
                        @foreach ($workload as $w)
                            <a href="{{ route('academics.timetable', ['teacher' => $w['id']]) }}" class="grid grid-cols-[minmax(0,1fr)_90px_28px] items-center gap-2 text-[12px] hover:text-brand">
                                <span class="truncate text-ink-2">{{ $w['name'] }}</span>
                                <span class="h-1.5 overflow-hidden rounded-full bg-sunken"><span class="block h-full rounded-full {{ $w['periods'] > 24 ? 'bg-bad' : ($w['periods'] >= 18 ? 'bg-brass' : 'bg-brand') }}" style="width: {{ $w['periods'] / $maxLoad * 100 }}%"></span></span>
                                <span class="mono text-right text-ink">{{ $w['periods'] }}</span>
                            </a>
                        @endforeach
                    </div>
                    <div class="border-t border-line px-5 py-2.5 text-[11px] text-muted">Above 24 periods a week is shown in red.</div>
                </section>
            @endif
        </aside>

        {{-- Slot modal --}}
        @if ($editable)
            <template x-teleport="body">
                <div x-show="open" x-cloak class="fixed inset-0 z-50 grid place-items-center bg-black/40 px-4" @click.self="open=false" @keydown.escape.window="open=false">
                    <div class="panel rise w-full max-w-md shadow-2xl">
                        <div class="panel-head">
                            <div class="panel-title" x-text="mode === 'add' ? 'Schedule a period' : 'Move or remove period'"></div>
                            <button type="button" class="text-muted hover:text-ink" @click="open=false"><x-icon name="x" :size="16" /></button>
                        </div>
                        <form method="POST" :action="action()" class="space-y-4 p-5">
                            @csrf
                            <template x-if="mode === 'edit'"><input type="hidden" name="_method" value="PUT"></template>
                            <div>
                                <label class="field-label">Course <span class="req">*</span></label>
                                <select name="course_id" class="input" x-model="form.course_id" required>
                                    <option value="">Select course…</option>
                                    @foreach ($courses as $c)<option value="{{ $c->id }}">{{ $c->subject->name }} — {{ $c->teacher?->name ?? 'no teacher' }}</option>@endforeach
                                </select>
                            </div>
                            <div class="grid grid-cols-3 gap-3">
                                <div class="col-span-3">
                                    <label class="field-label">Day <span class="req">*</span></label>
                                    <select name="weekday" class="input" x-model="form.weekday" required>
                                        @foreach ($days as $d => $n)<option value="{{ $d }}">{{ $n }}</option>@endforeach
                                    </select>
                                </div>
                                <div><label class="field-label">Starts <span class="req">*</span></label><input type="time" name="starts_at" class="input" x-model="form.starts_at" required></div>
                                <div><label class="field-label">Ends <span class="req">*</span></label><input type="time" name="ends_at" class="input" x-model="form.ends_at" required></div>
                                <div><label class="field-label">Room <span class="req">*</span></label><input name="room" class="input" x-model="form.room" required></div>
                            </div>
                            <p class="text-[12px] text-muted">The section and the teacher are both checked for clashes before saving.</p>
                            <div class="flex items-center justify-between gap-2 pt-1">
                                <span>
                                    <button x-show="mode === 'edit'" type="submit" form="slot-delete" class="btn btn-danger btn-sm"><x-icon name="trash" :size="14" /> Remove</button>
                                </span>
                                <button class="btn btn-primary"><x-icon name="check" :size="15" /> <span x-text="mode === 'add' ? 'Add period' : 'Save changes'"></span></button>
                            </div>
                        </form>
                        <form id="slot-delete" method="POST" :action="@js(url('academics/timetable/slots')) + '/' + editId" class="hidden">@csrf @method('DELETE')</form>
                    </div>
                </div>
            </template>
        @endif
    </div>
</x-layouts.app>
