<x-layouts.app title="Attendance defaulters">
    <x-ui.page-header eyebrow="Attendance" title="Defaulters"
        :description="'Students below the '.$threshold.'% attendance required to sit board and semester exams, and those within '.$margin.' points of it.'">
        <x-slot:actions>
            <a href="{{ route('attendance.defaulters.export', request()->query()) }}" class="btn btn-ghost"><x-icon name="download" :size="15" /> Export CSV</a>
        </x-slot:actions>
    </x-ui.page-header>

    <div class="mb-5 flex flex-wrap items-end justify-between gap-4">
        <div class="tabs" role="tablist">
            <a role="tab" href="{{ route('attendance.defaulters', array_merge(request()->except('tab', 'page'), ['tab' => 'defaulters'])) }}" aria-selected="{{ $tab === 'defaulters' ? 'true' : 'false' }}" class="tab">
                Below {{ $threshold }}% <span class="mono rounded bg-bad-soft px-1.5 text-[11px] text-bad">{{ $counts['defaulters'] }}</span>
            </a>
            <a role="tab" href="{{ route('attendance.defaulters', array_merge(request()->except('tab', 'page'), ['tab' => 'risk'])) }}" aria-selected="{{ $tab === 'risk' ? 'true' : 'false' }}" class="tab">
                At risk ({{ $threshold }}–{{ $threshold + $margin }}%) <span class="mono rounded bg-warn-soft px-1.5 text-[11px] text-warn">{{ $counts['risk'] }}</span>
            </a>
        </div>
        <form method="GET" class="flex flex-wrap items-center gap-2">
            <input type="hidden" name="tab" value="{{ $tab }}">
            <div class="relative">
                <x-icon name="search" :size="15" class="absolute left-3 top-1/2 -translate-y-1/2 text-muted" />
                <input type="search" name="q" value="{{ $q }}" placeholder="Name or roll no" class="input !h-9 !w-48 pl-9">
            </div>
            <select name="program_id" class="input !h-9 !w-36" data-autosubmit>
                <option value="">All programs</option>
                @foreach ($options['programs'] as $id => $code)<option value="{{ $id }}" @selected(request('program_id') == $id)>{{ $code }}</option>@endforeach
            </select>
            <select name="section_id" class="input !h-9 !w-48" data-autosubmit>
                <option value="">All classes</option>
                @foreach ($options['sections'] as $id => $label)<option value="{{ $id }}" @selected(request('section_id') == $id)>{{ $label }}</option>@endforeach
            </select>
            <button class="btn btn-ghost !h-9">Filter</button>
        </form>
    </div>

    <form method="POST" action="{{ route('attendance.defaulters.notify') }}" x-data="{ picked: [], all: false }" class="panel overflow-hidden">
        @csrf
        <div class="panel-head">
            <div class="text-[12.5px] text-muted">
                <span class="mono font-semibold text-ink">{{ $rows->count() }}</span> of {{ $counts['total'] }} students ·
                <span x-show="picked.length" x-cloak><span class="mono text-ink" x-text="picked.length"></span> selected</span>
                <span x-show="!picked.length">select students to notify their guardians</span>
            </div>
            <button class="btn btn-brass btn-sm" :disabled="!picked.length" disabled><x-icon name="bell" :size="14" /> Notify guardians</button>
        </div>
        @error('student_ids')<div class="border-b border-line bg-bad-soft px-4 py-2 text-[12.5px] text-bad">{{ $message }}</div>@enderror

        @if ($rows->isEmpty())
            <x-ui.empty icon="check" :title="$tab === 'risk' ? 'Nobody is hovering near the line' : 'No defaulters — every student is above '.$threshold.'%'"
                        description="Keep marking registers daily; this list updates the moment a student drops below the requirement.">
                <a href="{{ route('attendance.analytics') }}" class="btn btn-ghost">View analytics</a>
            </x-ui.empty>
        @else
            <div class="overflow-x-auto">
                <table class="table">
                    <thead>
                        <tr>
                            <th class="w-10"><input type="checkbox" class="checkbox" aria-label="Select all" @change="picked = $event.target.checked ? @js($rows->map(fn ($r) => (string) $r['student']->id)) : []"></th>
                            <th>Student</th>
                            <th>Class</th>
                            <th class="num">Attendance</th>
                            <th class="num">Absent</th>
                            <th class="num">Longest streak</th>
                            <th>Last present</th>
                            <th>8-week trend</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rows as $r)
                            @php $s = $r['student']; $gap = $threshold - $r['rate']; @endphp
                            <tr>
                                <td><input type="checkbox" class="checkbox" name="student_ids[]" value="{{ $s->id }}" x-model="picked"></td>
                                <td>
                                    <div class="flex items-center gap-2.5">
                                        <x-ui.avatar :name="$s->name" :size="30" />
                                        <div class="leading-tight">
                                            <a href="{{ route('attendance.student', $s) }}" class="font-semibold text-ink hover:text-brand">{{ $s->name }}</a>
                                            <div class="mono text-[11px] text-muted">{{ $s->roll_no }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td class="whitespace-nowrap">{{ $s->section?->label() }}</td>
                                <td class="num">
                                    <div class="mono text-[14px] font-semibold {{ $r['rate'] < $threshold ? 'text-bad' : 'text-warn' }}">{{ number_format($r['rate'], 1) }}%</div>
                                    <div class="text-[11px] text-muted">{{ $gap > 0 ? number_format($gap, 1).' pts short' : number_format(-$gap, 1).' pts spare' }}</div>
                                </td>
                                <td class="num mono">{{ $r['A'] }}<span class="text-muted">/{{ $r['counted'] }}</span></td>
                                <td class="num"><span class="mono {{ $r['streak']['longest'] >= config('attendance.consecutive_absence_alert') ? 'font-semibold text-bad' : '' }}">{{ $r['streak']['longest'] }}d</span>
                                    @if ($r['streak']['current'] > 0)<div class="text-[11px] text-bad">absent now · {{ $r['streak']['current'] }}d</div>@endif
                                </td>
                                <td class="whitespace-nowrap text-[12.5px]">{{ $r['streak']['last_present'] ? \Carbon\Carbon::parse($r['streak']['last_present'])->format('D, j M') : '—' }}</td>
                                <td><x-ui.sparkline :values="$r['weekly']" :width="96" :height="28" :tone="$r['rate'] < $threshold ? 'bad' : 'warn'" /></td>
                                <td class="text-right"><a href="{{ route('attendance.student', $s) }}" class="btn btn-ghost btn-sm">Profile</a></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </form>
</x-layouts.app>
