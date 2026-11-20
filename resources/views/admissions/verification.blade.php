<x-layouts.app title="AI verification">
    <x-ui.page-header eyebrow="Admissions" title="AI verification queue"
        description="Applications where the documents and the form disagree. Clear ones move to Verified on their own — only exceptions land here." />

    <x-admissions.subnav />

    @unless ($aiEnabled)
        <div class="panel mb-4 flex items-start gap-3 border-warn/30 bg-warn-soft px-4 py-3 text-[13px] text-warn">
            <x-icon name="info" :size="16" class="mt-0.5" />
            <div><b>AI reading is switched off.</b> Add <span class="mono">ANTHROPIC_API_KEY</span> to the server <span class="mono">.env</span> to read new uploads automatically. Until then, uploads are marked “manual” and staff confirm values by hand.</div>
        </div>
    @endunless

    @php
        $tabs = [
            'mismatch' => ['Mismatch', 'bad', 'alert'],
            'review' => ['Needs a look', 'warn', 'eye'],
            'clear' => ['Docs match', 'ok', 'shield'],
            'pending' => ['Not checked', 'muted', 'clock'],
        ];
    @endphp
    <div class="mb-5 grid gap-3 sm:grid-cols-4">
        @foreach ($tabs as $key => [$label, $tone, $icon])
            <a href="{{ route('admissions.verification', ['tab' => $key]) }}"
               class="panel flex items-center gap-3 p-4 transition {{ $tab === $key ? '!border-ink' : 'hover:border-line-strong' }}">
                <span class="grid h-10 w-10 place-items-center rounded-xl" style="background: var(--{{ $tone }}-soft, var(--sunken)); color: var(--{{ $tone }})"><x-icon :name="$icon" :size="18" /></span>
                <div>
                    <div class="numeral text-[26px] text-ink">{{ $counts[$key] ?? 0 }}</div>
                    <div class="eyebrow !text-[9.5px]">{{ $label }}</div>
                </div>
            </a>
        @endforeach
    </div>

    @if ($apps->isEmpty())
        <div class="panel">
            <x-ui.empty :title="$tab === 'mismatch' ? 'No mismatches — every document agrees' : 'Nothing in this list'"
                        description="New applications are checked automatically as soon as they are submitted." icon="shield">
                <a href="{{ route('admissions.index') }}" class="btn btn-ghost">Browse all applications</a>
            </x-ui.empty>
        </div>
    @else
        <div class="grid gap-3 lg:grid-cols-2">
            @foreach ($apps as $a)
                <div class="panel flex flex-col p-4">
                    <div class="flex items-start gap-3">
                        <x-ui.avatar :name="$a->name ?? '?'" :size="38" />
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center justify-between gap-2">
                                <a href="{{ route('admissions.show', $a) }}" class="truncate text-[14px] font-semibold text-ink hover:text-brand">{{ $a->name }}</a>
                                <x-admissions.score :score="$a->verification_score" :status="$a->verification_status" />
                            </div>
                            <div class="mt-0.5 flex flex-wrap items-center gap-x-2 text-[11.5px] text-muted">
                                <span class="mono">{{ $a->tracking_no }}</span><span>·</span><span>{{ $a->program?->code }}</span><span>·</span><span>{{ $a->submitted_at?->diffForHumans() }}</span>
                            </div>
                        </div>
                    </div>

                    <ul class="mt-3 flex-1 space-y-1.5">
                        @forelse ($a->checks->sortBy(fn ($c) => ['mismatch' => 0, 'missing' => 1, 'partial' => 2][$c->result] ?? 3)->take(4) as $c)
                            <li class="flex items-start gap-2 rounded-lg px-2.5 py-1.5 text-[12.5px]" style="background: var(--{{ $c->result === 'mismatch' ? 'bad' : 'warn' }}-soft)">
                                <x-icon :name="$c->result === 'mismatch' ? 'x' : 'alert'" :size="13" class="mt-0.5 {{ $c->result === 'mismatch' ? 'text-bad' : 'text-warn' }}" />
                                <span class="min-w-0"><b class="text-ink">{{ $c->label }}</b> <span class="text-ink-2">— {{ $c->note }}</span></span>
                            </li>
                        @empty
                            <li class="text-[12.5px] text-muted">All checks passed.</li>
                        @endforelse
                    </ul>

                    <div class="mt-3 flex items-center justify-between border-t border-line pt-3">
                        <div class="flex gap-1">
                            @foreach ($a->documents->where('type', '!=', 'photo') as $d)
                                <span class="grid h-6 min-w-6 place-items-center rounded-md px-1 text-[9.5px] font-bold uppercase tracking-wide"
                                      style="background: var(--{{ ['done' => 'ok', 'failed' => 'bad', 'skipped' => 'warn'][$d->extraction_status] ?? 'info' }}-soft); color: var(--{{ ['done' => 'ok', 'failed' => 'bad', 'skipped' => 'warn'][$d->extraction_status] ?? 'info' }})"
                                      title="{{ $d->label() }} · {{ $d->extraction_status }}{{ $d->confidence ? ' · '.$d->confidence.'%' : '' }}">{{ ['cnic' => 'ID', 'father_cnic' => 'FID', 'matric_dmc' => 'SSC', 'inter_dmc' => 'HSC', 'domicile' => 'DOM', 'character_certificate' => 'CC'][$d->type] ?? '?' }}</span>
                            @endforeach
                        </div>
                        <a href="{{ route('admissions.show', $a) }}" class="btn btn-primary btn-sm">Review <x-icon name="arrow-right" :size="13" /></a>
                    </div>
                </div>
            @endforeach
        </div>
        <div class="mt-4">{{ $apps->links() }}</div>
    @endif
</x-layouts.app>
