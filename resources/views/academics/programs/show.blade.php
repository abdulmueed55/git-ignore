<x-layouts.app :title="$program->name">
    <x-ui.page-header :eyebrow="$program->code.' · '.$program->levelLabel()" :title="$program->name" :description="$program->eligibility">
        <x-slot:actions>
            <a href="{{ route('academics.programs.index') }}" class="btn btn-ghost"><x-icon name="arrow-left" :size="15" /> Programs</a>
            <a href="{{ route('academics.programs.edit', $program) }}" class="btn btn-primary"><x-icon name="edit" :size="15" /> Edit</a>
        </x-slot:actions>
    </x-ui.page-header>

    @php $w = $program->weights(); @endphp
    <div class="mb-6 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <x-ui.stat label="Active students" :value="$program->students_count" icon="users" :hint="'of '.$program->seats.' seats'" />
        <x-ui.stat label="Sections" :value="$program->sections->count()" icon="layers" />
        <x-ui.stat label="Annual fee" :value="'Rs '.number_format($program->annual_fee)" icon="book" />
        <div class="panel p-5">
            <div class="eyebrow">Merit formula</div>
            <div class="mt-3 space-y-1.5 text-[12.5px]">
                @if ($program->basedOnInter())
                    <div class="flex justify-between"><span class="text-muted">Intermediate marks</span><span class="mono text-ink">× {{ $w['academic'] }}</span></div>
                    <div class="flex justify-between"><span class="text-muted">Matric marks</span><span class="mono text-ink">× {{ $w['matric'] ?? 0 }}</span></div>
                @else
                    <div class="flex justify-between"><span class="text-muted">Matric marks</span><span class="mono text-ink">× 1</span></div>
                @endif
                <div class="flex justify-between"><span class="text-muted">Hafiz-e-Quran bonus</span><span class="mono text-brass">+{{ $w['hafiz_bonus'] }}</span></div>
            </div>
        </div>
    </div>

    <section class="panel">
        <div class="panel-head">
            <div class="panel-title">Sections</div>
            <a href="{{ route('academics.sections.create', ['program' => $program->id]) }}" class="btn btn-ghost btn-sm"><x-icon name="plus" :size="14" /> Add section</a>
        </div>
        @if ($program->sections->count())
            <div class="overflow-x-auto">
                <table class="table">
                    <thead><tr><th>Section</th><th>Session</th><th>Room</th><th>Incharge</th><th class="num">Courses</th><th>Strength</th></tr></thead>
                    <tbody>
                    @foreach ($program->sections as $s)
                        <tr>
                            <td><a href="{{ route('academics.sections.show', $s) }}" class="font-semibold text-ink hover:text-brand">{{ $s->name }}</a></td>
                            <td class="mono text-[12px]">{{ $s->academicSession?->name }}</td>
                            <td>{{ $s->room }}</td>
                            <td>{{ $s->incharge?->name ?? '—' }}</td>
                            <td class="num">{{ $s->courses_count }}</td>
                            <td class="w-48">
                                <div class="flex items-center gap-2">
                                    <div class="h-1.5 flex-1 overflow-hidden rounded-full bg-sunken"><div class="h-full rounded-full bg-brand" style="width: {{ $s->capacity ? min(100, $s->students_count / $s->capacity * 100) : 0 }}%"></div></div>
                                    <span class="mono text-[11.5px]">{{ $s->students_count }}/{{ $s->capacity }}</span>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <x-ui.empty icon="layers" title="No sections in this program" description="Create a section for the current session, then assign subjects and teachers from the timetable.">
                <a href="{{ route('academics.sections.create', ['program' => $program->id]) }}" class="btn btn-primary"><x-icon name="plus" :size="15" /> Add section</a>
            </x-ui.empty>
        @endif
    </section>
</x-layouts.app>
