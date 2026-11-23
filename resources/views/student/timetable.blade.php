<x-layouts.student title="Timetable">
    <div class="eyebrow">{{ $student->section?->label() }}</div>
    <h1 class="mt-1 text-[24px] font-extrabold">My week</h1>
    <p class="mt-1 text-[13.5px] text-muted">Regular periods plus any extra class, make-up class or class test your teachers schedule.</p>

    <div class="mt-5 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        @foreach ($days as $day)
            <section class="panel {{ $day['date']->isToday() ? 'ring-2 ring-brand/40' : '' }}">
                <div class="panel-head">
                    <div class="panel-title">{{ $day['date']->isToday() ? 'Today' : $day['date']->format('l') }}</div>
                    <span class="mono text-[12px] text-muted">{{ $day['date']->format('j M') }}</span>
                </div>
                @if ($day['holiday'])
                    <p class="px-4 py-4 text-[13px] text-warn">Holiday — {{ $day['holiday'] }}</p>
                @elseif ($day['items']->isEmpty())
                    <p class="px-4 py-4 text-[13px] text-muted">No classes.</p>
                @else
                    <ul class="divide-y divide-line">
                        @foreach ($day['items'] as $i)
                            <li class="flex gap-3 px-4 py-2.5 text-[13px] {{ $i->end->isPast() ? 'opacity-55' : '' }}">
                                <span class="mono w-11 shrink-0 font-semibold">{{ $i->start->format('H:i') }}</span>
                                <span class="min-w-0 flex-1">
                                    <span class="font-semibold">{{ $i->course->subject->name }}</span>
                                    @if ($i->type === 'extra')<span class="badge badge-brass">{{ $i->label }}</span>@endif
                                    <span class="block truncate text-muted">Room {{ $i->room }} · {{ $i->course->teacher?->name ?? '—' }}</span>
                                </span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>
        @endforeach
    </div>
</x-layouts.student>
