<x-admissions.public title="Application status">
    @if (! $app)
        <div class="mx-auto grid max-w-4xl gap-8 lg:grid-cols-[1fr_380px] lg:items-center">
            <div>
                <div class="eyebrow flex items-center gap-2"><span class="h-px w-5 bg-brass"></span>Applicants</div>
                <h1 class="display mt-3 text-[40px] leading-tight text-ink">Check your application</h1>
                <p class="mt-3 max-w-md text-[14px] leading-relaxed text-muted">See where your application stands, whether your documents were accepted, and your merit list position. Unfinished applications open where you left off.</p>
            </div>
            <form method="POST" action="{{ route('apply.status.lookup') }}" class="panel space-y-4 p-6">
                @csrf
                <x-ui.field name="tracking_no" label="Tracking number" required :value="request('tracking_no')" placeholder="FGLA-26-XXXXX" class="mono" autocomplete="off" />
                <x-ui.field name="pin" label="6-digit PIN" required inputmode="numeric" maxlength="6" placeholder="••••••" autocomplete="off" />
                <button class="btn btn-primary h-11 w-full">Open application <x-icon name="arrow-right" :size="16" /></button>
                <p class="text-center text-[12px] text-muted">Lost your PIN? Visit the admission office with your CNIC / B-Form.</p>
            </form>
        </div>
    @else
        @php
            $flow = ['submitted' => 'Submitted', 'verified' => 'Documents verified', 'selected' => 'Selected in merit list', 'admitted' => 'Admitted'];
            $rank = ['draft' => 0, 'submitted' => 1, 'under_review' => 1, 'flagged' => 1, 'verified' => 2, 'waitlisted' => 2, 'selected' => 3, 'admitted' => 4, 'rejected' => 1];
            $at = $rank[$app->status] ?? 1;
            $problems = $app->checks->where('overridden', false)->where('result', 'mismatch')->whereNotIn('field', ['duplicate_cnic']);
        @endphp
        <div class="mx-auto max-w-3xl">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <div class="eyebrow">{{ $app->cycle->title }}</div>
                    <h1 class="display mt-1 text-[32px] text-ink">{{ $app->name }}</h1>
                    <div class="mt-1 text-[13px] text-muted">{{ $app->program?->name }} · {{ $app->program?->admissionClass() }} · <span class="mono">{{ $app->tracking_no }}</span></div>
                </div>
                <x-ui.status :value="$app->status" :label="$app->statusLabel()" class="!h-7 !px-3 !text-[12.5px]" />
            </div>

            @if ($app->status === 'draft')
                <div class="panel mt-6 flex flex-wrap items-center justify-between gap-3 p-5">
                    <div class="text-[13.5px] text-ink">Your application is not submitted yet.</div>
                    <a href="{{ route('apply.documents', $app->tracking_no) }}" class="btn btn-primary">Continue application</a>
                </div>
            @else
                <div class="panel mt-6 p-6">
                    <ol class="grid gap-4 sm:grid-cols-4">
                        @foreach (array_values($flow) as $i => $label)
                            @php $reached = $at >= $i + 1 && $app->status !== 'rejected'; @endphp
                            <li class="relative">
                                <div class="h-1.5 rounded-full {{ $reached ? 'bg-brand' : 'bg-sunken' }}"></div>
                                <div class="mt-2 flex items-center gap-1.5 text-[12.5px] font-semibold {{ $reached ? 'text-ink' : 'text-muted' }}">
                                    @if ($reached)<x-icon name="check" :size="13" class="text-brand" />@endif {{ $label }}
                                </div>
                            </li>
                        @endforeach
                    </ol>

                    @if ($app->status === 'admitted' && $app->student)
                        <div class="mt-6 rounded-xl border border-ok/30 bg-ok-soft p-5">
                            <div class="eyebrow !text-ok">Welcome to FG Liaquat Ali Degree College</div>
                            <div class="mt-1 text-[15px] text-ink">You are admitted to <b>{{ $app->student->section?->label() ?? $app->program->name }}</b>. Your roll number is <b class="mono">{{ $app->student->roll_no }}</b>.</div>
                            <div class="mt-3 rounded-lg bg-surface p-4 text-[13.5px]">
                                <div class="font-semibold text-ink">Your student portal is ready</div>
                                <p class="mt-1 text-ink-2">Attendance, timetable, extra classes, leave and this admission record — on your phone. Sign in with your <b>roll number</b> and <b>the same 6-digit PIN</b> you used here, then choose your own password.</p>
                                <a href="{{ route('student.login') }}" class="btn btn-primary mt-3"><x-icon name="arrow-right" :size="15" /> Open student portal</a>
                            </div>
                        </div>
                    @elseif ($entry)
                        <div class="mt-6 rounded-xl border border-ok/30 bg-ok-soft p-5">
                            <div class="eyebrow !text-ok">Congratulations — you are selected</div>
                            <div class="mt-1 text-[15px] text-ink">You are <b>#{{ $entry->position }}</b> on merit list {{ $entry->meritList->list_no }} for {{ $entry->meritList->program->name }} (merit <span class="mono">{{ number_format($entry->merit_score, 2) }}</span>).</div>
                            <div class="mt-3 text-[13px] font-semibold text-ink">What to do next</div>
                            <ol class="mt-1 list-decimal space-y-1 pl-5 text-[13px] text-ink-2">
                                <li>Deposit the admission fee by <b>{{ $entry->meritList->fee_deadline?->format('l, j F Y') }}</b>.</li>
                                <li>Bring the originals of the documents below and two photographs to the admission office.</li>
                                <li>The office admits you and gives your roll number. Your <b>student portal</b> opens the same day — sign in with the roll number and this PIN.</li>
                            </ol>
                        </div>
                    @elseif ($app->status === 'waitlisted')
                        <div class="mt-6 rounded-xl border border-warn/30 bg-warn-soft p-4 text-[13px] text-ink">You are on the waiting list. Watch for the next merit list{{ $app->merit_score ? ' — your merit is '.number_format($app->merit_score, 2) : '' }}.</div>
                    @elseif ($app->status === 'rejected')
                        <div class="mt-6 rounded-xl border border-bad/30 bg-bad-soft p-4 text-[13px] text-bad">{{ $app->remarks ?: 'Your application was not accepted.' }}</div>
                    @elseif ($app->status === 'flagged')
                        <div class="mt-6 rounded-xl border border-warn/30 bg-warn-soft p-4">
                            <div class="text-[13.5px] font-semibold text-warn">The admission office needs to check something</div>
                            <ul class="mt-1 space-y-0.5 text-[13px] text-ink-2">
                                @forelse ($problems as $p)<li>• {{ $p->label }} differs between your form and documents</li>@empty<li>• One of your documents could not be confirmed</li>@endforelse
                            </ul>
                            <div class="mt-1 text-[12.5px] text-muted">{{ $app->remarks ?: 'Bring your original documents to the admission office, or wait for a call.' }}</div>
                        </div>
                    @else
                        <div class="mt-6 text-[13px] text-muted">{{ $app->remarks ?: ($app->status === 'under_review' ? 'Your documents are being checked by the admission office. No action needed right now.' : 'No action needed right now. Merit lists appear here when published.') }}</div>
                    @endif
                </div>

                <div class="panel mt-4 p-6">
                    <div class="eyebrow mb-3">Your application</div>
                    <dl class="grid grid-cols-[140px_1fr] gap-x-3 gap-y-1.5 text-[13.5px]">
                        <dt class="text-muted">Name</dt><dd class="font-semibold text-ink">{{ $app->name }}</dd>
                        @if ($app->father_name)<dt class="text-muted">Father</dt><dd class="text-ink">{{ $app->father_name }}</dd>@endif
                        <dt class="text-muted">Programme</dt><dd class="text-ink">{{ $app->program?->name }} <span class="text-muted">· {{ $app->program?->admissionClass() }}</span></dd>
                        <dt class="text-muted">Quota</dt><dd class="text-ink">{{ \App\Models\AdmissionApplication::QUOTAS[$app->quota] ?? $app->quota }}</dd>
                        @if ($app->matric_total)<dt class="text-muted">Matric</dt><dd class="mono text-ink">{{ $app->matric_obtained }} / {{ $app->matric_total }}</dd>@endif
                        @if ($app->inter_total)<dt class="text-muted">Intermediate</dt><dd class="mono text-ink">{{ $app->inter_obtained }} / {{ $app->inter_total }}</dd>@endif
                        @if ($app->merit_score)<dt class="text-muted">Merit score</dt><dd class="mono font-semibold text-ink">{{ number_format($app->merit_score, 2) }}</dd>@endif
                        <dt class="text-muted">Submitted</dt><dd class="text-ink">{{ $app->submitted_at?->format('j M Y, g:i A') ?? '—' }}</dd>
                    </dl>
                </div>

                <div class="panel mt-4 p-6">
                    <div class="eyebrow mb-3">Documents received</div>
                    <ul class="grid gap-2 sm:grid-cols-2">
                        @foreach ($app->documents as $d)
                            <li class="flex items-center justify-between gap-2 rounded-lg bg-sunken px-3 py-2 text-[13px]">
                                <span class="text-ink">{{ $d->label() }}</span>
                                @if ($d->extraction_status === 'failed')<span class="text-[12px] text-bad">unclear</span>@else<x-icon name="check" :size="14" class="text-ok" />@endif
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </div>
    @endif
</x-admissions.public>
