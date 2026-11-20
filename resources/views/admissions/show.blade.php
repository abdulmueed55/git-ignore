<x-layouts.app :title="$app->name ?? $app->tracking_no">
    @php
        $auto = $app->autofilled_fields ?? [];
        $photo = $docs->get('photo');
        $busy = $app->documents->contains(fn ($d) => in_array($d->extraction_status, ['pending', 'processing'], true));
        $integrity = $app->checks->filter(fn ($c) => ! array_key_exists($c->field, $fieldMap));
        $docLabels = ['cnic' => 'CNIC / B-Form', 'father_cnic' => "Father's CNIC", 'matric_dmc' => 'Matric DMC', 'inter_dmc' => 'Inter DMC', 'domicile' => 'Domicile'];
        $cellTone = fn (?int $s) => $s === null ? '' : ($s >= \App\Services\Admissions\CrossDocumentVerifier::MATCH_AT ? 'ok' : ($s >= \App\Services\Admissions\CrossDocumentVerifier::PARTIAL_AT ? 'warn' : 'bad'));
        $fmt = function ($v) {
            if ($v instanceof \DateTimeInterface) return $v->format('j M Y');
            if (is_string($v) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $v)) return \Carbon\Carbon::parse($v)->format('j M Y');
            return $v;
        };
    @endphp

    {{-- Header --}}
    <div class="mb-6 flex flex-wrap items-start justify-between gap-5">
        <div class="flex min-w-0 items-start gap-4">
            @if ($photo)
                <img src="{{ route('admissions.documents.show', $photo) }}" alt="Photo of {{ $app->name }}" class="h-[84px] w-[70px] shrink-0 rounded-lg border border-line object-cover">
            @else
                <x-ui.avatar :name="$app->name ?? '?'" :size="64" />
            @endif
            <div class="min-w-0">
                <a href="{{ route('admissions.index', ['cycle' => $app->admission_cycle_id]) }}" class="eyebrow mb-2 flex items-center gap-2 hover:text-ink"><x-icon name="arrow-left" :size="12" /> Applications · {{ $app->cycle->title }}</a>
                <h1 class="display text-[30px] leading-tight text-ink">{{ $app->name ?? 'Unnamed draft' }}</h1>
                <div class="mt-2 flex flex-wrap items-center gap-2 text-[12.5px] text-muted">
                    <span class="mono rounded-md border border-line bg-surface px-2 py-0.5 text-ink">{{ $app->tracking_no }}</span>
                    <x-ui.status :value="$app->status" :label="$app->statusLabel()" />
                    <x-admissions.vstatus :value="$busy ? 'processing' : $app->verification_status" />
                    <span>{{ $app->program?->name }} · {{ $app->program?->admissionClass() }}</span>
                    @if ($app->submitted_at)<span>· submitted {{ $app->submitted_at->format('j M Y, g:i a') }}</span>@endif
                </div>
            </div>
        </div>

        <div class="flex flex-wrap items-center gap-2" x-data="{ open: false }">
            <form method="POST" action="{{ route('admissions.reverify', $app) }}">
                @csrf
                <button class="btn btn-ghost"><x-icon name="refresh" :size="15" /> Re-read documents</button>
            </form>
            @if (in_array($app->status, ['selected', 'verified', 'waitlisted']) && ! $app->student)
                <form method="POST" action="{{ route('admissions.admit', $app) }}">
                    @csrf
                    <button class="btn btn-primary"><x-icon name="graduation" :size="15" /> Admit &amp; issue roll no</button>
                </form>
            @endif
            @if ($app->status !== 'admitted')
                <div class="relative">
                    <button class="btn btn-ghost" @click="open = !open"><x-icon name="edit" :size="15" /> Decision <x-icon name="chevron-down" :size="14" /></button>
                    <form x-show="open" x-cloak @click.outside="open=false" method="POST" action="{{ route('admissions.status', $app) }}" class="panel rise absolute right-0 z-20 mt-2 w-[300px] space-y-3 p-4 shadow-xl">
                        @csrf @method('PATCH')
                        <x-ui.field name="status" label="Set status" type="select" required :empty="false" :value="in_array($app->status, ['under_review','verified','flagged','waitlisted','rejected']) ? $app->status : 'under_review'"
                            :options="['under_review' => 'Under review', 'verified' => 'Verified', 'flagged' => 'Flagged', 'waitlisted' => 'Waitlisted', 'rejected' => 'Rejected']" />
                        <x-ui.field name="remarks" label="Remarks (shown to applicant)" type="textarea" rows="3" placeholder="Required when rejecting or flagging" :value="$app->remarks" />
                        <button class="btn btn-primary w-full">Save decision</button>
                    </form>
                </div>
            @endif
        </div>
    </div>

    @if ($busy)
        <div class="panel mb-4 flex items-center gap-3 border-info/30 bg-info-soft px-4 py-3 text-[13px] text-info"
             x-data x-init="const t = setInterval(async () => { const r = await fetch('{{ route('admissions.progress', $app) }}', {headers: {Accept: 'application/json'}}); const j = await r.json(); if (!j.busy) { clearInterval(t); location.reload(); } }, 2000)">
            <x-icon name="cpu" :size="16" /> AI is reading the documents — this page refreshes automatically.
        </div>
    @endif
    @if ($app->remarks)
        <div class="panel mb-4 flex items-start gap-3 px-4 py-3 text-[13px]"><x-icon name="info" :size="16" class="mt-0.5 text-brass" /><div><span class="font-semibold text-ink">Remarks:</span> <span class="text-ink-2">{{ $app->remarks }}</span></div></div>
    @endif

    {{-- Side-by-side comparison --}}
    <div class="panel overflow-hidden">
        <div class="panel-head">
            <div>
                <div class="panel-title">Form vs documents</div>
                <div class="mt-0.5 text-[12px] text-muted">What the applicant entered, next to what the AI read from each upload.</div>
            </div>
            <div class="flex items-center gap-3">
                <div class="hidden items-center gap-3 text-[11px] text-muted sm:flex">
                    <span class="flex items-center gap-1"><span class="h-2 w-2 rounded-sm bg-ok"></span>match</span>
                    <span class="flex items-center gap-1"><span class="h-2 w-2 rounded-sm bg-warn"></span>spelling</span>
                    <span class="flex items-center gap-1"><span class="h-2 w-2 rounded-sm bg-bad"></span>differs</span>
                </div>
                <x-admissions.score :score="$app->verification_score" :status="$app->verification_status" class="w-28" />
            </div>
        </div>
        <div class="overflow-x-auto">
            <table class="table">
                <thead>
                <tr>
                    <th class="min-w-[150px]">Field</th>
                    <th class="min-w-[150px]">Form</th>
                    @foreach ($compareDocs as $t)
                        <th class="min-w-[130px]">
                            {{ $docLabels[$t] }}
                            @if (! $docs->has($t))<span class="ml-1 normal-case tracking-normal text-bad">· missing</span>
                            @elseif ($docs[$t]->extraction_status === 'failed')<span class="ml-1 normal-case tracking-normal text-bad">· unreadable</span>
                            @elseif ($docs[$t]->extraction_status === 'skipped')<span class="ml-1 normal-case tracking-normal text-warn">· manual</span>@endif
                        </th>
                    @endforeach
                    <th>Result</th>
                </tr>
                </thead>
                <tbody>
                @foreach ($fieldMap as $field => [$label, $kind, $sources])
                    @php
                        if (in_array($field, \App\Services\Admissions\FieldMap::INTER_FIELDS) && ! $needsInter) continue;
                        $check = $checks->get($field);
                        $formVal = $app->{$field};
                        $formStr = $formVal instanceof \DateTimeInterface ? $formVal->format('Y-m-d') : (string) ($formVal ?? '');
                        $hasAny = $formStr !== '' || collect($sources)->keys()->contains(fn ($t) => filled($docs->get($t)?->extracted[$sources[$t]] ?? null));
                        if (! $hasAny) continue;
                    @endphp
                    <tr>
                        <td class="whitespace-nowrap font-semibold text-ink">{{ $label }}</td>
                        <td class="whitespace-nowrap">
                            <span class="{{ in_array($kind, ['digits','number','date']) ? 'mono text-[12.5px]' : '' }} whitespace-nowrap text-ink">{{ $formStr !== '' ? ($field === 'gender' ? ucfirst($formStr) : $fmt($formStr)) : '—' }}</span>
                            @if (in_array($field, $auto))<x-admissions.autofill-dot class="ml-1" />@endif
                        </td>
                        @foreach ($compareDocs as $t)
                            @php
                                $key = $sources[$t] ?? null;
                                $doc = $docs->get($t);
                                $val = $key && $doc && $doc->extraction_status === 'done' ? ($doc->extracted[$key] ?? null) : null;
                                $s = ($key && filled($val) && $formStr !== '') ? \App\Services\Admissions\CrossDocumentVerifier::compare($kind, $formStr, (string) $val) : null;
                                $tone = $cellTone($s);
                            @endphp
                            <td>
                                @if (! $key)
                                    <span class="text-line-strong">·</span>
                                @elseif (blank($val))
                                    <span class="text-[12px] text-muted">{{ $doc ? 'not read' : '—' }}</span>
                                @else
                                    <span class="inline-block whitespace-nowrap rounded-md px-1.5 py-0.5 {{ in_array($kind, ['digits','number','date']) ? 'mono text-[12.5px]' : '' }}"
                                          @if($tone) style="background: var(--{{ $tone }}-soft); color: var(--{{ $tone === 'ok' ? 'ink' : $tone }})" @endif
                                          title="{{ $s !== null ? $s.'% similar' : '' }}">{{ $fmt($val) }}</span>
                                @endif
                            </td>
                        @endforeach
                        <td>
                            @if ($check)
                                @if ($check->overridden)
                                    <span class="badge badge-info badge-plain" title="{{ $check->note }}"><x-icon name="check" :size="11" /> Accepted</span>
                                @else
                                    <x-ui.status :value="$check->result" :label="$check->result === 'partial' ? 'Spelling '.$check->similarity.'%' : ucfirst($check->result)" />
                                @endif
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-4 grid grid-cols-1 gap-4 xl:grid-cols-[1fr_340px]">
        <div class="min-w-0 space-y-4">
            {{-- Problems & integrity checks --}}
            <div class="panel">
                <div class="panel-head">
                    <div class="panel-title">Findings</div>
                    <span class="text-[12px] text-muted">{{ $app->checks->where('result', 'match')->count() }} passed · {{ $app->checks->where('result', '!=', 'match')->count() }} to review</span>
                </div>
                @php $findings = $app->checks->sortBy(fn ($c) => [($c->overridden ? 3 : ['mismatch' => 0, 'missing' => 1, 'partial' => 2, 'match' => 4][$c->result]), $c->id]); @endphp
                <ul>
                    @forelse ($findings->filter(fn ($c) => $c->result !== 'match' || ! array_key_exists($c->field, $fieldMap)) as $c)
                        <li class="flex flex-wrap items-start gap-3 border-b border-line px-4 py-3 last:border-0" x-data="{ ov: false }">
                            <span class="mt-0.5 grid h-6 w-6 shrink-0 place-items-center rounded-full {{ $c->overridden ? 'bg-info-soft text-info' : match($c->result) { 'match' => 'bg-ok-soft text-ok', 'partial' => 'bg-warn-soft text-warn', 'missing' => 'bg-warn-soft text-warn', default => 'bg-bad-soft text-bad' } }}">
                                <x-icon :name="$c->overridden || $c->result === 'match' ? 'check' : ($c->result === 'mismatch' ? 'x' : 'alert')" :size="12" />
                            </span>
                            <div class="min-w-0 flex-1">
                                <div class="text-[13px] font-semibold text-ink">{{ $c->label }}</div>
                                <div class="text-[12.5px] text-muted">{{ $c->note ?? ($c->result === 'match' ? 'Consistent across documents.' : '') }}</div>
                                @if ($c->overridden)
                                    <div class="mt-1 text-[11.5px] text-info">Accepted by staff</div>
                                @endif
                            </div>
                            @if (! $c->overridden && $c->result !== 'match')
                                <button type="button" class="btn btn-ghost btn-sm" @click="ov = !ov">Accept…</button>
                                <form x-show="ov" x-cloak method="POST" action="{{ route('admissions.checks.override', [$app, $c]) }}" class="flex w-full gap-2 pl-9">
                                    @csrf
                                    <input name="note" required minlength="5" class="input !h-9 flex-1" placeholder="Why is this acceptable? e.g. NADRA spelling, verified original">
                                    <button class="btn btn-primary btn-sm !h-9">Accept</button>
                                </form>
                            @endif
                        </li>
                    @empty
                        <li><x-ui.empty title="No findings yet" description="Upload documents or re-read them to run the cross-check." icon="scan" /></li>
                    @endforelse
                </ul>
            </div>

            {{-- Documents --}}
            <div class="panel">
                <div class="panel-head">
                    <div class="panel-title">Uploaded documents</div>
                    <span class="text-[12px] text-muted">{{ count(array_intersect($app->requiredDocumentTypes(), $app->documents->pluck('type')->all())) }} of {{ count($app->requiredDocumentTypes()) }} required uploaded</span>
                </div>
                <div class="grid gap-4 p-4 sm:grid-cols-2 2xl:grid-cols-3">
                    @foreach (array_unique(array_merge($app->requiredDocumentTypes(), $app->documents->pluck('type')->all())) as $type)
                        @php $d = $docs->get($type); @endphp
                        <div class="overflow-hidden rounded-xl border border-line bg-paper">
                            @if ($d)
                                <a href="{{ route('admissions.documents.show', $d) }}" target="_blank" class="block aspect-[16/10] overflow-hidden bg-sunken">
                                    @if ($d->isImage())
                                        <img src="{{ route('admissions.documents.show', $d) }}" alt="{{ $d->label() }}" loading="lazy" class="h-full w-full object-cover object-top transition hover:scale-[1.02]">
                                    @else
                                        <div class="grid h-full place-items-center text-muted"><x-icon name="file" :size="34" /><span class="text-[12px]">PDF</span></div>
                                    @endif
                                </a>
                                <div class="p-3">
                                    <div class="flex items-center justify-between gap-2">
                                        <div class="truncate text-[12.5px] font-semibold text-ink">{{ $d->label() }}</div>
                                        <x-ui.status :value="$d->extraction_status" :label="['done' => 'Read', 'skipped' => $d->type === 'photo' ? 'Photo' : 'Manual', 'failed' => 'Unreadable', 'pending' => 'Queued', 'processing' => 'Reading'][$d->extraction_status] ?? $d->extraction_status" />
                                    </div>
                                    <div class="mt-1 flex items-center justify-between text-[11px] text-muted">
                                        <span>{{ number_format($d->size / 1024) }} KB @if($d->extracted_by) · {{ $d->extracted_by }}@endif</span>
                                        @if ($d->confidence)<span class="mono">{{ $d->confidence }}% conf.</span>@endif
                                    </div>
                                    @if ($d->extraction_error)
                                        <div class="mt-2 rounded-md bg-bad-soft px-2 py-1.5 text-[11.5px] text-bad">{{ $d->extraction_error }}</div>
                                    @endif
                                    @if ($d->extracted)
                                        <details class="mt-2 text-[11.5px]">
                                            <summary class="cursor-pointer font-semibold text-brand">Extracted data</summary>
                                            <dl class="mt-2 space-y-1">
                                                @foreach ($d->extracted as $k => $v)
                                                    @continue(in_array($k, ['confidence']))
                                                    <div class="flex justify-between gap-3">
                                                        <dt class="text-muted">{{ str_replace('_', ' ', $k) }}</dt>
                                                        <dd class="mono truncate text-right text-ink">{{ is_array($v) ? (implode('; ', $v) ?: 'none') : (is_bool($v) ? ($v ? 'yes' : 'no') : ($v ?? '—')) }}</dd>
                                                    </div>
                                                @endforeach
                                            </dl>
                                        </details>
                                    @endif
                                </div>
                            @else
                                <div class="grid aspect-[16/10] place-items-center stripe-brass text-center">
                                    <div><x-icon name="upload" :size="22" class="mx-auto text-brass" /><div class="mt-1 text-[12px] font-semibold text-ink">Not uploaded</div></div>
                                </div>
                                <div class="p-3 text-[12.5px] font-semibold text-ink">{{ \App\Models\AdmissionApplication::DOCUMENT_TYPES[$type] }}</div>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        {{-- Sidebar --}}
        <div class="space-y-4">
            <div class="panel p-5">
                <div class="eyebrow">Merit</div>
                <div class="mt-2 flex items-baseline gap-2">
                    <span class="numeral text-[46px] text-ink">{{ $merit['score'] !== null ? number_format($merit['score'], 2) : '—' }}</span>
                    @if ($merit['score'] !== null)<span class="text-[13px] text-muted">/ 100</span>@endif
                </div>
                <dl class="mt-3 space-y-1.5 border-t border-line pt-3 text-[12.5px]">
                    @forelse ($merit['parts'] as $part)
                        <div class="flex justify-between gap-3"><dt class="text-muted">{{ $part['label'] }}</dt><dd class="mono text-right text-ink">{{ $part['value'] }}</dd></div>
                    @empty
                        <div class="text-muted">Marks not entered yet — merit appears once result-card marks are available.</div>
                    @endforelse
                </dl>
                @if ($entry)
                    <a href="{{ route('admissions.merit.show', $entry->meritList) }}" class="mt-4 flex items-center justify-between rounded-lg bg-brand-soft px-3 py-2 text-[12.5px] text-brand">
                        <span>Merit list #{{ $entry->meritList->list_no }} · position <b class="mono">{{ $entry->position }}</b></span>
                        <x-icon name="arrow-right" :size="14" />
                    </a>
                @endif
            </div>

            @if ($app->student)
                <div class="panel border-ok/30 p-5">
                    <div class="eyebrow !text-ok">Enrolled</div>
                    <div class="mono mt-2 text-[20px] font-semibold text-ink">{{ $app->student->roll_no }}</div>
                    <div class="mt-1 text-[12.5px] text-muted">{{ $app->student->section?->label() ?? 'Section to be assigned' }}</div>
                    @if (Route::has('students.show'))
                        <a href="{{ route('students.show', $app->student) }}" class="btn btn-ghost btn-sm mt-3">Open student record</a>
                    @endif
                </div>
            @endif

            <div class="panel p-5" x-data="{ edit: {{ $errors->any() ? 'true' : 'false' }} }">
                <div class="flex items-center justify-between">
                    <div class="eyebrow">Applicant</div>
                    <button class="text-[12px] font-semibold text-brand hover:underline" @click="edit = !edit" x-text="edit ? 'Cancel' : 'Correct details'"></button>
                </div>
                <dl x-show="!edit" class="mt-3 space-y-2 text-[12.5px]">
                    @foreach ([
                        'Programme' => $app->program?->name.($app->secondProgram ? ' (2nd: '.$app->secondProgram->code.')' : ''),
                        'Quota' => (\App\Models\AdmissionApplication::QUOTAS[$app->quota] ?? $app->quota).($app->is_hafiz ? ' · Hafiz-e-Quran' : ''),
                        'Gender' => ucfirst($app->gender ?? '—'),
                        'Date of birth' => $app->date_of_birth?->format('j M Y'),
                        'Religion' => $app->religion,
                        'Phone' => $app->phone,
                        'Guardian' => $app->guardian_phone,
                        'Email' => $app->email,
                        'Address' => trim($app->address.', '.$app->city, ', '),
                        'Domicile' => $app->domicile,
                    ] as $k => $v)
                        <div class="flex justify-between gap-4"><dt class="shrink-0 text-muted">{{ $k }}</dt><dd class="text-right text-ink">{{ $v ?: '—' }}</dd></div>
                    @endforeach
                </dl>
                <form x-show="edit" x-cloak method="POST" action="{{ route('admissions.update', $app) }}" class="mt-3 space-y-3">
                    @csrf @method('PUT')
                    <x-ui.field name="program_id" label="Programme" type="select" required :value="$app->program_id" :options="$programs->pluck('code', 'id')->all()" :empty="false" />
                    <x-ui.field name="name" label="Name" required :value="$app->name" />
                    <x-ui.field name="father_name" label="Father's name" required :value="$app->father_name" />
                    <div class="grid grid-cols-2 gap-2">
                        <x-ui.field name="cnic" label="CNIC / B-Form" required :value="$app->cnic" />
                        <x-ui.field name="father_cnic" label="Father CNIC" required :value="$app->father_cnic" />
                        <x-ui.field name="date_of_birth" label="Date of birth" type="date" required :value="$app->date_of_birth?->format('Y-m-d')" />
                        <x-ui.field name="gender" label="Gender" type="select" required :value="$app->gender" :options="['male' => 'Male', 'female' => 'Female']" />
                        <x-ui.field name="phone" label="Phone" required :value="$app->phone" />
                        <x-ui.field name="guardian_phone" label="Guardian" required :value="$app->guardian_phone" />
                        <x-ui.field name="domicile" label="Domicile" required :value="$app->domicile" />
                        <x-ui.field name="quota" label="Quota" type="select" required :value="$app->quota" :options="\App\Models\AdmissionApplication::QUOTAS" :empty="false" />
                        <x-ui.field name="matric_board" label="Matric board" required :value="$app->matric_board" />
                        <x-ui.field name="matric_roll_no" label="Matric roll" required :value="$app->matric_roll_no" />
                        <x-ui.field name="matric_year" label="Matric year" type="number" required :value="$app->matric_year" />
                        <x-ui.field name="matric_total" label="Matric total" type="number" required :value="$app->matric_total" />
                        <x-ui.field name="matric_obtained" label="Matric obtained" type="number" required :value="$app->matric_obtained" class="col-span-2" />
                        @if ($needsInter)
                            <x-ui.field name="inter_board" label="Inter board" :value="$app->inter_board" />
                            <x-ui.field name="inter_roll_no" label="Inter roll" :value="$app->inter_roll_no" />
                            <x-ui.field name="inter_year" label="Inter year" type="number" :value="$app->inter_year" />
                            <x-ui.field name="inter_total" label="Inter total" type="number" :value="$app->inter_total" />
                            <x-ui.field name="inter_obtained" label="Inter obtained" type="number" :value="$app->inter_obtained" class="col-span-2" />
                        @endif
                    </div>
                    <label class="flex items-center gap-2 text-[12.5px]"><input type="hidden" name="is_hafiz" value="0"><input type="checkbox" name="is_hafiz" value="1" class="checkbox" @checked(old('is_hafiz', $app->is_hafiz))> Hafiz-e-Quran</label>
                    <x-ui.field name="reason" label="Reason for correction" required placeholder="e.g. Verified original DMC at counter" />
                    <button class="btn btn-primary w-full">Save &amp; re-verify</button>
                </form>
            </div>

            <div class="panel p-5">
                <div class="eyebrow mb-3">Timeline</div>
                <ol class="relative space-y-4 border-l border-line pl-4">
                    @foreach ($timeline as $log)
                        <li class="relative">
                            <span class="absolute -left-[21px] top-1 h-2.5 w-2.5 rounded-full border-2 border-surface bg-brass"></span>
                            <div class="text-[12.5px] text-ink">{{ $log->description }}</div>
                            <div class="text-[11px] text-muted">{{ $log->user?->name ?? 'Applicant' }} · {{ $log->created_at->diffForHumans() }}</div>
                        </li>
                    @endforeach
                    <li class="relative">
                        <span class="absolute -left-[21px] top-1 h-2.5 w-2.5 rounded-full border-2 border-surface bg-line-strong"></span>
                        <div class="text-[12.5px] text-ink">Application started online</div>
                        <div class="text-[11px] text-muted">{{ $app->created_at->format('j M Y, g:i a') }}</div>
                    </li>
                </ol>
            </div>
        </div>
    </div>
</x-layouts.app>
