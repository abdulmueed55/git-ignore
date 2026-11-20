<x-layouts.app :title="'Register · '.$section->label()">
    @push('head')
        <style>
            @media print {
                aside, header, footer, .no-print { display: none !important; }
                body, main { background: #fff !important; padding: 0 !important; }
                .lg\:pl-\[256px\] { padding-left: 0 !important; }
                .print-sheet { border: 0 !important; overflow: visible !important; }
                .print-sheet table { font-size: 9px; }
                @page { size: A4 landscape; margin: 10mm; }
            }
        </style>
    @endpush
    @php $cell = ['P' => 'text-ok', 'L' => 'text-warn', 'A' => 'bg-bad-soft text-bad font-bold', 'E' => 'text-info']; @endphp

    <x-ui.page-header eyebrow="Monthly register" :title="$section->label().' · '.$month->format('F Y')"
        :description="$course ? $course->subject->name.' — one column per lecture' : 'All subjects — one column per working day (absent = missed every period that day)'">
        <x-slot:actions>
            <div class="no-print flex gap-2">
                <a href="{{ route('attendance.register.export', request()->query()) }}" class="btn btn-ghost"><x-icon name="download" :size="15" /> CSV</a>
                <button type="button" onclick="window.print()" class="btn btn-primary"><x-icon name="printer" :size="15" /> Print</button>
            </div>
        </x-slot:actions>
    </x-ui.page-header>

    <form method="GET" class="no-print panel mb-5 flex flex-wrap items-end gap-3 p-4">
        <x-ui.field name="section_id" label="Class" type="select" :options="$sections->mapWithKeys(fn ($s) => [$s->id => $s->label()])" :value="$section->id" :empty="false" class="w-full sm:w-56" onchange="this.form.submit()" />
        <x-ui.field name="course_id" label="Subject" type="select" :options="$courses->mapWithKeys(fn ($c) => [$c->id => $c->subject->name])" :value="$course?->id"
                    :empty="auth()->user()->isLeadership() ? 'All subjects (day sheet)' : false" class="w-full sm:w-56" />
        <x-ui.field name="month" label="Month" type="month" :value="$month->format('Y-m')" class="w-full sm:w-44" max="{{ today()->format('Y-m') }}" />
        <button class="btn btn-primary">Show</button>
    </form>

    <div class="panel print-sheet overflow-hidden">
        @if (empty($columns))
            <x-ui.empty icon="calendar" title="No registers in {{ $month->format('F Y') }}"
                        description="Nothing was marked for this class{{ $course ? ' and subject' : '' }} in this month. Pick another month or subject.">
                <a href="{{ route('attendance.register', ['section_id' => $section->id, 'course_id' => $course?->id, 'month' => today()->format('Y-m')]) }}" class="btn btn-primary">This month</a>
            </x-ui.empty>
        @else
            <div class="scrollbar-thin overflow-x-auto">
                <table class="w-full border-collapse text-[11.5px]">
                    <thead>
                        <tr class="border-b border-line-strong">
                            <th class="sticky left-0 z-10 bg-surface px-3 py-2 text-left"><span class="eyebrow">#</span></th>
                            <th class="sticky left-8 z-10 min-w-[180px] bg-surface px-3 py-2 text-left"><span class="eyebrow">Student</span></th>
                            @foreach ($columns as $c)
                                <th class="border-l border-line px-0.5 py-1.5 text-center font-normal" title="{{ $c['label'] }}{{ $c['topic'] ? ' — '.$c['topic'] : '' }}">
                                    <div class="mono text-[11px] font-semibold text-ink">{{ $c['day'] }}</div>
                                    <div class="text-[9.5px] text-muted">{{ $c['dow'] }}</div>
                                </th>
                            @endforeach
                            <th class="border-l border-line-strong px-2 text-center"><span class="eyebrow">P</span></th>
                            <th class="px-2 text-center"><span class="eyebrow">A</span></th>
                            <th class="px-2 text-center"><span class="eyebrow">E</span></th>
                            <th class="px-3 text-right"><span class="eyebrow">%</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($students as $i => $s)
                            @php $t = $totals[$s->id]; @endphp
                            <tr class="border-b border-line hover:bg-sunken/50">
                                <td class="mono sticky left-0 bg-surface px-3 py-1.5 text-muted">{{ $i + 1 }}</td>
                                <td class="sticky left-8 bg-surface px-3 py-1.5">
                                    <a href="{{ route('attendance.student', $s) }}" class="font-semibold text-ink hover:text-brand">{{ $s->name }}</a>
                                    <span class="mono ml-1 text-[10px] text-muted">{{ $s->roll_no }}</span>
                                </td>
                                @foreach ($columns as $c)
                                    @php $v = $grid[$s->id][$c['key']] ?? null; @endphp
                                    <td class="mono border-l border-line text-center {{ $v ? $cell[$v] : 'text-line-strong' }}">{{ $v ?? '·' }}</td>
                                @endforeach
                                <td class="mono border-l border-line-strong text-center text-ok">{{ $t['p'] }}</td>
                                <td class="mono text-center text-bad">{{ $t['a'] }}</td>
                                <td class="mono text-center text-info">{{ $t['e'] }}</td>
                                <td class="mono px-3 text-right font-semibold {{ $t['rate'] !== null && $t['rate'] < $threshold ? 'text-bad' : 'text-ink' }}">{{ $t['rate'] ?? '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="flex flex-wrap items-center justify-between gap-3 border-t border-line px-4 py-3 text-[11.5px] text-muted">
                <span><span class="mono text-ok">P</span> present · <span class="mono text-warn">L</span> late · <span class="mono text-bad">A</span> absent · <span class="mono text-info">E</span> leave · % excludes leave</span>
                <span>Class in-charge: {{ $section->incharge?->name ?? '—' }} · Printed {{ now()->format('j M Y, H:i') }}</span>
            </div>
        @endif
    </div>
</x-layouts.app>
