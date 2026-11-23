<x-layouts.student title="Attendance">
    @php $rate = $overall['rate']; @endphp
    <div class="eyebrow">My attendance</div>
    <h1 class="mt-1 text-[24px] font-extrabold">{{ $rate !== null ? round($rate, 1).'%' : 'No registers yet' }} <span class="text-[15px] font-semibold text-muted">overall</span></h1>
    <p class="mt-1 text-[13.5px] text-muted">Late counts as present. Approved leave does not count against you. At least {{ $threshold }}% is required in every subject to sit the exams.</p>

    <section class="panel mt-5 overflow-hidden">
        <div class="panel-head"><div class="panel-title">By subject</div></div>
        @if ($subjects->isEmpty())
            <x-ui.empty icon="book" title="No subjects yet" description="Subjects appear once you are placed in a class section. Ask the admission office if this takes more than a week.">
                <a href="{{ route('student.home') }}" class="btn btn-ghost btn-sm">Back to home</a>
            </x-ui.empty>
        @else
            <ul class="divide-y divide-line">
                @foreach ($subjects as $row)
                    @php $r = $row['r']; $cr = $r['rate']; @endphp
                    <li class="px-4 py-3">
                        <div class="flex items-center gap-3">
                            <div class="min-w-0 flex-1">
                                <div class="text-[14px] font-semibold">{{ $row['course']->subject->name }}</div>
                                <div class="mono text-[12px] text-muted">Present {{ $r['P'] }} · Late {{ $r['L'] }} · Absent {{ $r['A'] }} · Leave {{ $r['E'] }}</div>
                            </div>
                            <span class="mono text-[16px] font-bold {{ $cr === null ? 'text-muted' : ($cr < $threshold ? 'text-bad' : 'text-ok') }}">{{ $cr !== null ? round($cr).'%' : '—' }}</span>
                        </div>
                        <div class="mt-2 h-2 overflow-hidden rounded-full bg-sunken" role="img" aria-label="{{ $cr ?? 0 }} percent">
                            <div class="h-full rounded-full {{ $cr !== null && $cr < $threshold ? 'bg-bad' : 'bg-ok' }}" style="width: {{ min(100, $cr ?? 0) }}%"></div>
                        </div>
                        @if ($row['need'] > 0)
                            <div class="mt-2 text-[12.5px] text-bad">Attend the next <b>{{ $row['need'] }}</b> {{ $row['need'] === 1 ? 'class' : 'classes' }} of {{ $row['course']->subject->name }} without missing one to get back to {{ $threshold }}%.</div>
                        @endif
                    </li>
                @endforeach
            </ul>
        @endif
    </section>

    <section class="panel mt-5 overflow-hidden">
        <div class="panel-head"><div class="panel-title">Latest registers</div><span class="eyebrow">newest first</span></div>
        @if ($recent->isEmpty())
            <x-ui.empty icon="check" title="No registers yet" description="Each time a teacher marks your class, it shows here with your status.">
                <a href="{{ route('student.timetable') }}" class="btn btn-ghost btn-sm">See my timetable</a>
            </x-ui.empty>
        @else
            <ul class="divide-y divide-line">
                @foreach ($recent as $rec)
                    <li class="flex items-center gap-3 px-4 py-2.5 text-[13px]">
                        <span class="mono w-24 shrink-0 text-muted">{{ $rec->lecture->held_on->format('D j M') }}</span>
                        <span class="min-w-0 flex-1 truncate"><span class="font-semibold">{{ $rec->lecture->course->subject->name }}</span> <span class="mono text-muted">{{ substr($rec->lecture->starts_at, 0, 5) }}</span></span>
                        <x-ui.status :value="$rec->status" :label="config('attendance.statuses')[$rec->status] ?? $rec->status" />
                    </li>
                @endforeach
            </ul>
        @endif
    </section>
</x-layouts.student>
