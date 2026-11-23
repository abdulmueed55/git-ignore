<x-layouts.student title="My admission">
    <div class="eyebrow">Admission record</div>
    <h1 class="mt-1 text-[24px] font-extrabold">My admission</h1>

    @if (! $app)
        <div class="panel mt-5">
            <x-ui.empty icon="file" title="No online application on record" description="You were admitted before online applications started, so there is no application to show here. Your attendance, timetable and leave work as normal. For admission papers, visit the admission office.">
                <a href="{{ route('student.home') }}" class="btn btn-primary btn-sm">Back to home</a>
            </x-ui.empty>
        </div>
    @else
        @php
            $steps = [
                ['Applied online', $app->submitted_at],
                ['Documents verified', in_array($app->status, ['verified', 'waitlisted', 'selected', 'admitted'], true) ? ($app->reviewed_at ?? $app->submitted_at) : null],
                ['Selected in merit list', $entry?->meritList?->published_at],
                ['Admitted · roll number '.$student->roll_no, $app->status === 'admitted' ? ($student->created_at) : null],
            ];
            $cnic = $app->cnic ? substr($app->cnic, 0, 6).'•••••'.substr($app->cnic, -3) : '—';
        @endphp

        <section class="panel mt-5 p-5">
            <ol class="grid gap-4 sm:grid-cols-4">
                @foreach ($steps as $i => [$label, $when])
                    <li class="flex gap-3 sm:flex-col sm:gap-2">
                        <span class="grid h-8 w-8 shrink-0 place-items-center rounded-full text-[13px] font-bold {{ $when ? 'bg-ok text-paper' : 'bg-sunken text-muted' }}">{{ $when ? '✓' : $i + 1 }}</span>
                        <span><span class="block text-[13.5px] font-semibold">{{ $label }}</span><span class="block text-[12px] text-muted">{{ $when ? $when->format('j M Y') : 'Pending' }}</span></span>
                    </li>
                @endforeach
            </ol>
        </section>

        <div class="mt-5 grid gap-5 lg:grid-cols-2">
            <section class="panel">
                <div class="panel-head"><div class="panel-title">Application</div><span class="mono text-[12px] text-muted">{{ $app->tracking_no }}</span></div>
                <dl class="grid grid-cols-[130px_1fr] gap-x-3 gap-y-2 px-4 py-4 text-[13.5px]">
                    <dt class="text-muted">Name</dt><dd class="font-semibold">{{ $app->name }}</dd>
                    <dt class="text-muted">Father</dt><dd>{{ $app->father_name }}</dd>
                    <dt class="text-muted">CNIC / B-Form</dt><dd class="mono">{{ $cnic }}</dd>
                    <dt class="text-muted">Programme</dt><dd>{{ $app->program?->name }} <span class="text-muted">· {{ $app->program?->admissionClass() }}</span></dd>
                    <dt class="text-muted">Quota</dt><dd>{{ \App\Models\AdmissionApplication::QUOTAS[$app->quota] ?? $app->quota }}</dd>
                    <dt class="text-muted">Matric</dt><dd class="mono">{{ $app->matric_total ? $app->matric_obtained.' / '.$app->matric_total : '—' }}</dd>
                    @if ($app->inter_total)<dt class="text-muted">Intermediate</dt><dd class="mono">{{ $app->inter_obtained }} / {{ $app->inter_total }}</dd>@endif
                    <dt class="text-muted">Merit score</dt><dd class="mono font-semibold">{{ $app->merit_score ?? '—' }}</dd>
                    <dt class="text-muted">Status</dt><dd><x-ui.status :value="$app->status" :label="$app->statusLabel()" /></dd>
                </dl>
            </section>

            <section class="space-y-5">
                @if ($entry)
                    <div class="panel p-4">
                        <div class="eyebrow">Merit list</div>
                        <div class="mt-1 text-[15px] font-bold">{{ $entry->meritList->program->code }} · List #{{ $entry->meritList->list_no }} · Position {{ $entry->position }}</div>
                        <div class="text-[12.5px] text-muted">Published {{ $entry->meritList->published_at?->format('j M Y') }} · closing merit {{ $entry->meritList->closing_merit }}</div>
                    </div>
                @endif
                <div class="panel">
                    <div class="panel-head"><div class="panel-title">My documents</div></div>
                    @forelse ($app->documents as $d)
                        <a href="{{ route('student.application.file', $d->type) }}" target="_blank" rel="noopener" class="flex items-center gap-3 border-b border-line px-4 py-2.5 text-[13px] last:border-0 hover:bg-sunken/50">
                            <x-icon name="file" :size="16" class="text-muted" />
                            <span class="min-w-0 flex-1 truncate">{{ $d->label() }}</span>
                            <span class="text-[12px] font-semibold text-brand">View</span>
                        </a>
                    @empty
                        <p class="px-4 py-4 text-[13px] text-muted">No documents were uploaded online.</p>
                    @endforelse
                </div>
            </section>
        </div>
    @endif
</x-layouts.student>
