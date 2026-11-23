<x-layouts.app title="Leave requests">
    <x-ui.page-header eyebrow="Attendance" title="Leave requests"
        description="Approving a leave converts the student’s absences in that period to “Leave”, which does not count against their attendance." />

    <div class="grid gap-6 xl:grid-cols-[1fr_360px]">
        <div class="min-w-0">
            <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                <div class="tabs" role="tablist">
                    @foreach (['pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected', 'all' => 'All'] as $k => $label)
                        <a role="tab" href="{{ route('attendance.leaves.index', ['status' => $k]) }}" aria-selected="{{ $status === $k ? 'true' : 'false' }}" class="tab">
                            {{ $label }}
                            @if ($k !== 'all')<span class="mono text-[11px] text-muted">{{ $counts[$k] ?? 0 }}</span>@endif
                        </a>
                    @endforeach
                </div>
                <form method="GET" class="relative">
                    <input type="hidden" name="status" value="{{ $status }}">
                    <x-icon name="search" :size="15" class="absolute left-3 top-1/2 -translate-y-1/2 text-muted" />
                    <input type="search" name="q" value="{{ request('q') }}" placeholder="Student or roll no" class="input !h-9 !w-56 pl-9">
                </form>
            </div>

            <div class="panel overflow-hidden">
                @if ($leaves->isEmpty())
                    <x-ui.empty icon="leaf" :title="$status === 'pending' ? 'No leave requests waiting' : 'No leave requests here'"
                                description="File a leave on behalf of a student using the form — for example when a parent sends an application or medical certificate.">
                        <a href="#file" class="btn btn-primary">File a leave</a>
                    </x-ui.empty>
                @else
                    <ul class="divide-y divide-line">
                        @foreach ($leaves as $l)
                            <li class="flex flex-wrap items-center gap-4 px-4 py-3.5">
                                <div class="w-14 shrink-0 rounded-lg border border-line bg-sunken/60 py-1.5 text-center leading-tight">
                                    <div class="numeral text-[20px]">{{ $l->from_date->format('j') }}</div>
                                    <div class="eyebrow !text-[9px]">{{ $l->from_date->format('M') }}</div>
                                </div>
                                <div class="min-w-0 flex-1">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <a href="{{ route('attendance.student', $l->student) }}" class="font-semibold text-ink hover:text-brand">{{ $l->student->name }}</a>
                                        <span class="mono text-[11px] text-muted">{{ $l->student->roll_no }}</span>
                                        <span class="text-[11.5px] text-muted">· {{ $l->student->section?->label() }}</span>
                                    </div>
                                    <div class="mt-0.5 text-[12.5px] text-ink-2">@if ($l->source === 'student')<span class="badge badge-info !h-[18px] !text-[10px]">Asked by student</span> @endif{{ $l->reason }}</div>
                                    <div class="mt-1 text-[11.5px] text-muted">
                                        {{ $l->from_date->format('D j M') }}{{ $l->days() > 1 ? ' → '.$l->to_date->format('D j M') : '' }} · {{ $l->days() }} {{ \Illuminate\Support\Str::plural('day', $l->days()) }}
                                        @if ($l->decider) · {{ ucfirst($l->status) }} by {{ $l->decider->name }} @else · filed {{ $l->created_at->diffForHumans() }} @endif
                                    </div>
                                </div>
                                @if ($l->status === 'pending' && ! auth()->user()->isLeadership())
                                    <span class="badge badge-warn">Awaiting principal</span>
                                @elseif ($l->status === 'pending')
                                    <div class="flex gap-2">
                                        <form method="POST" action="{{ route('attendance.leaves.reject', $l) }}">@csrf @method('PATCH')<button class="btn btn-ghost btn-sm">Reject</button></form>
                                        <form method="POST" action="{{ route('attendance.leaves.approve', $l) }}">@csrf @method('PATCH')<button class="btn btn-primary btn-sm"><x-icon name="check" :size="14" /> Approve</button></form>
                                    </div>
                                @else
                                    <x-ui.status :value="$l->status" />
                                @endif
                            </li>
                        @endforeach
                    </ul>
                    <div class="border-t border-line px-4 py-3">{{ $leaves->links() }}</div>
                @endif
            </div>
        </div>

        <form id="file" method="POST" action="{{ route('attendance.leaves.store') }}" class="panel self-start p-5">
            @csrf
            <div class="panel-title">File a leave</div>
            <p class="mb-4 mt-1 text-[12.5px] text-muted">On behalf of a student, from a parent’s application or medical certificate.</p>
            <div class="space-y-4">
                <div>
                    <label for="f_student_id" class="field-label">Student <span class="req">*</span></label>
                    <select id="f_student_id" name="student_id" class="input" required @error('student_id') aria-invalid="true" @enderror>
                        <option value="">Select a student…</option>
                        @foreach ($students as $group => $list)
                            <optgroup label="{{ $group }}">
                                @foreach ($list as $s)<option value="{{ $s->id }}" @selected(old('student_id') == $s->id)>{{ $s->roll_no }} — {{ $s->name }}</option>@endforeach
                            </optgroup>
                        @endforeach
                    </select>
                    @error('student_id')<div class="field-error">{{ $message }}</div>@enderror
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <x-ui.field name="from_date" type="date" label="From" required :value="today()->toDateString()" />
                    <x-ui.field name="to_date" type="date" label="To" required :value="today()->toDateString()" />
                </div>
                <x-ui.field name="reason" type="textarea" label="Reason" required rows="3" maxlength="255" placeholder="e.g. Fever — medical certificate from CMH attached" />
                <button class="btn btn-primary w-full"><x-icon name="plus" :size="15" /> File leave request</button>
            </div>
        </form>
    </div>
</x-layouts.app>
