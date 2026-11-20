<x-layouts.app title="Subjects">
    <x-ui.page-header eyebrow="Academics" title="Subjects" description="Catalogue of subjects, who teaches them and in which sections.">
        <x-slot:actions><a href="{{ route('academics.subjects.create') }}" class="btn btn-primary"><x-icon name="plus" :size="16" /> New subject</a></x-slot:actions>
    </x-ui.page-header>

    <div class="panel overflow-hidden">
        <form method="GET" class="flex flex-wrap gap-2 border-b border-line p-4" x-data>
            <div class="relative min-w-[240px] flex-1">
                <x-icon name="search" :size="16" class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-muted" />
                <input name="q" value="{{ request('q') }}" class="input pl-9" placeholder="Search by name or code">
            </div>
            <select name="department" class="input !w-auto min-w-[200px]" @change="$el.form.submit()">
                <option value="">All departments</option>
                @foreach ($departments as $id => $n)<option value="{{ $id }}" @selected(request('department') == $id)>{{ $n }}</option>@endforeach
            </select>
        </form>
        @if ($subjects->count())
            <div class="overflow-x-auto">
                <table class="table">
                    <thead><tr><th>Code</th><th>Subject</th><th>Department</th><th class="num">Credit hrs</th><th>Taught in</th><th></th></tr></thead>
                    <tbody>
                    @foreach ($subjects as $s)
                        <tr>
                            <td class="mono text-[12px] text-brass">{{ $s->code }}</td>
                            <td>
                                <div class="font-medium text-ink">{{ $s->name }}</div>
                                @if ($s->has_practical)<span class="text-[11.5px] text-info">Includes practical</span>@endif
                            </td>
                            <td><span class="inline-flex items-center gap-2"><x-academics.dept-swatch :code="$s->department?->code" small />{{ $s->department?->name }}</span></td>
                            <td class="num">{{ $s->credit_hours }}</td>
                            <td>
                                @if ($s->courses_count)
                                    <div class="flex max-w-md flex-wrap gap-1">
                                        @foreach ($s->courses->take(5) as $c)
                                            <span class="badge badge-plain" title="{{ $c->teacher?->name }}">{{ $c->section?->program?->code }} {{ Str::after($c->section?->name, '· ') ?: $c->section?->name }}</span>
                                        @endforeach
                                        @if ($s->courses_count > 5)<span class="badge badge-plain">+{{ $s->courses_count - 5 }}</span>@endif
                                    </div>
                                @else
                                    <span class="text-[12px] text-muted">Not scheduled</span>
                                @endif
                            </td>
                            <td class="text-right"><a href="{{ route('academics.subjects.edit', $s) }}" class="btn btn-ghost btn-sm"><x-icon name="edit" :size="14" /></a></td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <x-ui.empty icon="bookmark" title="No subjects match" description="Try another search or department, or add a new subject to the catalogue.">
                <a href="{{ route('academics.subjects.create') }}" class="btn btn-primary"><x-icon name="plus" :size="15" /> New subject</a>
            </x-ui.empty>
        @endif
    </div>
</x-layouts.app>
