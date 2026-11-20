<x-layouts.app title="Students">
    <x-ui.page-header eyebrow="Academics" title="Students"
        :description="auth()->user()->role === 'teacher' ? 'Students enrolled in the sections you teach.' : 'Every enrolled student across programs, sections and sessions.'">
        <x-slot:actions>
            <a href="{{ route('students.export', request()->query()) }}" class="btn btn-ghost"><x-icon name="download" :size="16" /> Export CSV</a>
            @if ($canEdit)
                <a href="{{ route('students.create') }}" class="btn btn-primary"><x-icon name="plus" :size="16" /> Enrol student</a>
            @endif
        </x-slot:actions>
    </x-ui.page-header>

    {{-- Status strip --}}
    <div class="mb-5 grid grid-cols-2 gap-3 sm:grid-cols-4 lg:grid-cols-5">
        <a href="{{ route('students.index', array_diff_key(request()->query(), ['status' => 1, 'page' => 1])) }}"
           class="panel p-4 transition hover:border-line-strong {{ empty($filters['status']) ? 'ring-1 ring-brass' : '' }}">
            <div class="eyebrow">All students</div>
            <div class="numeral mt-2 text-[28px]">{{ number_format($total) }}</div>
        </a>
        @foreach (\App\Http\Controllers\Academics\StudentController::STATUSES as $key => $label)
            <a href="{{ route('students.index', array_merge(request()->except('page'), ['status' => $key])) }}"
               class="panel p-4 transition hover:border-line-strong {{ ($filters['status'] ?? '') === $key ? 'ring-1 ring-brass' : '' }} {{ $loop->last ? 'hidden lg:block' : '' }}">
                <div class="flex items-center justify-between"><span class="eyebrow">{{ $label }}</span><x-ui.status :value="$key" :label="' '" class="!px-1.5" /></div>
                <div class="numeral mt-2 text-[28px] {{ ($statusCounts[$key] ?? 0) ? 'text-ink' : 'text-muted' }}">{{ number_format($statusCounts[$key] ?? 0) }}</div>
            </a>
        @endforeach
    </div>

    <div class="panel overflow-hidden">
        <form method="GET" class="grid gap-3 border-b border-line p-4 md:grid-cols-[minmax(220px,1.6fr)_repeat(4,minmax(0,1fr))_auto]" x-data>
            <div class="relative">
                <x-icon name="search" :size="16" class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-muted" />
                <input name="q" value="{{ $filters['q'] ?? '' }}" class="input pl-9" placeholder="Name, roll no, CNIC or father's name">
            </div>
            <select name="program" class="input" @change="$el.form.submit()">
                <option value="">All programs</option>
                @foreach ($programs as $id => $name)<option value="{{ $id }}" @selected(($filters['program'] ?? '') == $id)>{{ $name }}</option>@endforeach
            </select>
            <select name="section" class="input" @change="$el.form.submit()">
                <option value="">All sections</option>
                @foreach ($sections as $id => $name)<option value="{{ $id }}" @selected(($filters['section'] ?? '') == $id)>{{ $name }}</option>@endforeach
            </select>
            <select name="session" class="input" @change="$el.form.submit()">
                <option value="">All sessions</option>
                @foreach ($sessions as $id => $name)<option value="{{ $id }}" @selected(($filters['session'] ?? '') == $id)>{{ $name }}</option>@endforeach
            </select>
            <select name="gender" class="input" @change="$el.form.submit()">
                <option value="">Any gender</option>
                <option value="male" @selected(($filters['gender'] ?? '') === 'male')>Male</option>
                <option value="female" @selected(($filters['gender'] ?? '') === 'female')>Female</option>
            </select>
            @if (! empty($filters['status']))<input type="hidden" name="status" value="{{ $filters['status'] }}">@endif
            <div class="flex gap-2">
                <button class="btn btn-primary"><x-icon name="filter" :size="15" /> Apply</button>
                @if (array_filter($filters))
                    <a href="{{ route('students.index') }}" class="btn btn-ghost" title="Clear filters"><x-icon name="x" :size="15" /></a>
                @endif
            </div>
        </form>

        @if ($students->count())
            <div class="overflow-x-auto">
                <table class="table">
                    <thead>
                    <tr>
                        <th>Student</th>
                        <th>Roll no</th>
                        <th>Program · Section</th>
                        <th class="hidden lg:table-cell">CNIC / B-Form</th>
                        <th class="hidden xl:table-cell">Guardian</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach ($students as $s)
                        <tr>
                            <td>
                                <a href="{{ route('students.show', $s) }}" class="flex items-center gap-3">
                                    <x-ui.avatar :name="$s->name" :size="34" />
                                    <span class="min-w-0">
                                        <span class="block truncate font-semibold text-ink hover:text-brand">{{ $s->name }}</span>
                                        <span class="block truncate text-[12px] text-muted">{{ $s->gender === 'female' ? 'd/o' : 's/o' }} {{ $s->father_name }}</span>
                                    </span>
                                </a>
                            </td>
                            <td class="whitespace-nowrap"><span class="mono text-[12.5px] text-ink">{{ $s->roll_no }}</span></td>
                            <td>
                                <div class="text-ink">{{ $s->program?->code }}</div>
                                <div class="text-[12px] text-muted">{{ $s->section?->name ?? 'Unassigned' }}</div>
                            </td>
                            <td class="hidden lg:table-cell"><span class="mono text-[12px]">{{ $s->cnic }}</span></td>
                            <td class="hidden xl:table-cell"><span class="mono text-[12px]">{{ $s->guardian_phone }}</span></td>
                            <td><x-ui.status :value="$s->status" :label="\App\Http\Controllers\Academics\StudentController::STATUSES[$s->status] ?? null" /></td>
                            <td class="text-right">
                                <div class="flex justify-end gap-1">
                                    <a href="{{ route('students.show', $s) }}" class="btn btn-ghost btn-sm" title="Profile"><x-icon name="eye" :size="14" /></a>
                                    @if ($canEdit)
                                        <a href="{{ route('students.edit', $s) }}" class="btn btn-ghost btn-sm" title="Edit"><x-icon name="edit" :size="14" /></a>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            <div class="border-t border-line px-4 py-3">{{ $students->links() }}</div>
        @else
            <x-ui.empty icon="users" title="No students match these filters"
                description="Try a different name or roll number, or clear the filters to see everyone you have access to.">
                <a href="{{ route('students.index') }}" class="btn btn-ghost">Clear filters</a>
                @if ($canEdit)<a href="{{ route('students.create') }}" class="btn btn-primary"><x-icon name="plus" :size="15" /> Enrol student</a>@endif
            </x-ui.empty>
        @endif
    </div>
</x-layouts.app>
