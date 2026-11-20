<x-layouts.app title="Applications">
    <x-ui.page-header eyebrow="Admissions" title="Applications"
        description="Every submitted application with the values the AI read from documents. Values marked with a brass dot were auto-filled.">
        <x-slot:actions>
            <a href="{{ route('admissions.export', request()->query()) }}" class="btn btn-ghost"><x-icon name="download" :size="15" /> Export CSV</a>
            <a href="{{ route('apply.start') }}" target="_blank" class="btn btn-primary"><x-icon name="plus" :size="15" /> New application</a>
        </x-slot:actions>
    </x-ui.page-header>

    <x-admissions.subnav />

    <form method="GET" class="panel mb-4 grid gap-3 p-4 sm:grid-cols-2 lg:grid-cols-[1.6fr_repeat(5,1fr)_auto]">
        <div class="relative">
            <x-icon name="search" :size="15" class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-muted" />
            <input name="q" value="{{ request('q') }}" class="input !pl-9" placeholder="Name, CNIC, tracking no, phone">
        </div>
        <select name="cycle" class="input">
            <option value="all" @selected($currentCycle === 'all')>All cycles</option>
            @foreach ($cycles as $c)
                <option value="{{ $c->id }}" @selected((string) $currentCycle === (string) $c->id)>{{ \Illuminate\Support\Str::limit($c->title, 28) }}</option>
            @endforeach
        </select>
        <select name="program" class="input">
            <option value="">All programmes</option>
            @foreach ($programs as $p)
                <option value="{{ $p->id }}" @selected(request('program') == $p->id)>{{ $p->code }}</option>
            @endforeach
        </select>
        <select name="status" class="input">
            <option value="">Submitted (any)</option>
            @foreach (\App\Models\AdmissionApplication::STATUSES as $k => $v)
                <option value="{{ $k }}" @selected(request('status') === $k)>{{ $v }}</option>
            @endforeach
        </select>
        <select name="verification" class="input">
            <option value="">Any verification</option>
            @foreach (['clear' => 'Docs match', 'review' => 'Needs a look', 'mismatch' => 'Mismatch', 'pending' => 'Not checked'] as $k => $v)
                <option value="{{ $k }}" @selected(request('verification') === $k)>{{ $v }}</option>
            @endforeach
        </select>
        <select name="sort" class="input">
            @foreach (['recent' => 'Newest first', 'merit' => 'Merit: high → low', 'merit_asc' => 'Merit: low → high', 'score' => 'Weakest documents', 'name' => 'Name A–Z'] as $k => $v)
                <option value="{{ $k }}" @selected(request('sort', 'recent') === $k)>{{ $v }}</option>
            @endforeach
        </select>
        <div class="flex gap-2">
            <button class="btn btn-primary"><x-icon name="filter" :size="14" /> Apply</button>
            @if (request()->hasAny(['q', 'program', 'status', 'verification', 'sort', 'quota']))
                <a href="{{ route('admissions.index') }}" class="btn btn-ghost btn-icon" title="Clear filters"><x-icon name="x" :size="15" /></a>
            @endif
        </div>
    </form>

    <div class="panel overflow-hidden">
        @if ($apps->isEmpty())
            <x-ui.empty title="No applications match" description="Try clearing a filter or searching by CNIC / tracking number instead." icon="files">
                <a href="{{ route('admissions.index') }}" class="btn btn-ghost">Clear filters</a>
            </x-ui.empty>
        @else
            <div class="overflow-x-auto">
                <table class="table">
                    <thead>
                    <tr>
                        <th>Applicant</th>
                        <th>Programme</th>
                        <th>CNIC / B-Form</th>
                        <th class="num">Academic</th>
                        <th class="num">Merit</th>
                        <th>Documents</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach ($apps as $a)
                        @php
                            $auto = $a->autofilled_fields ?? [];
                            $inter = $a->program?->basedOnInter();
                            $lvl = $inter ? 'inter' : 'matric';
                        @endphp
                        <tr>
                            <td>
                                <a href="{{ route('admissions.show', $a) }}" class="flex items-center gap-3">
                                    <x-ui.avatar :name="$a->name ?? '?'" :size="32" />
                                    <div class="min-w-0">
                                        <div class="truncate font-semibold text-ink hover:text-brand">{{ $a->name ?? 'Unnamed draft' }}</div>
                                        <div class="truncate text-[11.5px] text-muted">{{ $a->gender === 'female' ? 'd/o' : 's/o' }} {{ $a->father_name ?? '—' }} @if(in_array('father_name', $auto))<x-admissions.autofill-dot />@endif</div>
                                    </div>
                                </a>
                            </td>
                            <td>
                                <div class="mono text-[12px] font-semibold text-ink">{{ $a->program?->code }}</div>
                                <div class="text-[11px] text-muted">{{ \App\Models\AdmissionApplication::QUOTAS[$a->quota] ?? $a->quota }}@if($a->is_hafiz) · Hafiz @endif</div>
                            </td>
                            <td><span class="mono text-[12px]">{{ $a->cnic ?? '—' }}</span></td>
                            <td class="num">
                                @if ($a->{$lvl.'_total'})
                                    <span class="mono text-[12px]">{{ $a->{$lvl.'_obtained'} }}<span class="text-muted">/{{ $a->{$lvl.'_total'} }}</span></span>
                                    @if(in_array($lvl.'_obtained', $auto))<x-admissions.autofill-dot />@endif
                                    <div class="text-[10.5px] uppercase tracking-wider text-muted">{{ $inter ? 'Inter' : 'Matric' }}</div>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td class="num"><span class="numeral text-[17px] text-ink">{{ $a->merit_score !== null ? number_format($a->merit_score, 2) : '—' }}</span></td>
                            <td>
                                <div class="space-y-1">
                                    <x-admissions.vstatus :value="$a->verification_status" />
                                    <x-admissions.score :score="$a->verification_score" :status="$a->verification_status" />
                                </div>
                            </td>
                            <td>
                                <x-ui.status :value="$a->status" :label="$a->statusLabel()" />
                                <div class="mono mt-1 text-[10.5px] text-muted">{{ $a->tracking_no }}</div>
                            </td>
                            <td class="text-right"><a href="{{ route('admissions.show', $a) }}" class="btn btn-ghost btn-sm">Open</a></td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            <div class="border-t border-line px-4 py-3">{{ $apps->links() }}</div>
        @endif
    </div>
</x-layouts.app>
