<x-layouts.app title="Admission cycles">
    <x-ui.page-header eyebrow="Admissions" title="Admission cycles" description="Each intake has its own window, fee, programmes and instructions. Only one cycle can be open at a time.">
        <x-slot:actions><a href="{{ route('admissions.cycles.create') }}" class="btn btn-primary"><x-icon name="plus" :size="15" /> New cycle</a></x-slot:actions>
    </x-ui.page-header>

    <x-admissions.subnav />

    @if ($cycles->isEmpty())
        <div class="panel"><x-ui.empty title="No admission cycles" description="Create the first intake to open the online application form." icon="calendar">
            <a href="{{ route('admissions.cycles.create') }}" class="btn btn-primary">Create cycle</a>
        </x-ui.empty></div>
    @else
        <div class="space-y-3">
            @foreach ($cycles as $c)
                <div class="panel grid items-center gap-4 p-5 md:grid-cols-[1.4fr_1fr_1fr_auto]">
                    <div class="min-w-0">
                        <div class="flex items-center gap-2">
                            <x-ui.status :value="$c->status" :label="$c->stateLabel()" />
                            <span class="mono text-[11.5px] text-muted">{{ $c->academicSession?->name }}</span>
                        </div>
                        <div class="display mt-1.5 text-[19px] text-ink">{{ $c->title }}</div>
                        <div class="mt-1 flex flex-wrap gap-1">
                            @foreach ($c->program_ids ?? [] as $pid)
                                <span class="badge badge-plain mono !text-[10.5px]">{{ $programs[$pid] ?? '?' }}</span>
                            @endforeach
                        </div>
                    </div>
                    <div class="text-[12.5px]">
                        <div class="text-muted">Window</div>
                        <div class="text-ink">{{ $c->opens_at->format('j M Y') }} → {{ $c->closes_at->format('j M Y') }}</div>
                        <div class="text-muted">Fee Rs {{ number_format($c->application_fee) }}</div>
                    </div>
                    <div class="grid grid-cols-3 gap-2 text-center">
                        <div><div class="numeral text-[22px]">{{ $c->submitted_count }}</div><div class="eyebrow !text-[9px]">Applied</div></div>
                        <div><div class="numeral text-[22px]">{{ $c->admitted_count }}</div><div class="eyebrow !text-[9px]">Admitted</div></div>
                        <div><div class="numeral text-[22px]">{{ $c->merit_lists_count }}</div><div class="eyebrow !text-[9px]">Lists</div></div>
                    </div>
                    <div class="flex gap-2">
                        <a href="{{ route('admissions.dashboard', ['cycle' => $c->id]) }}" class="btn btn-ghost btn-sm">Desk</a>
                        <a href="{{ route('admissions.cycles.edit', $c) }}" class="btn btn-ghost btn-sm"><x-icon name="edit" :size="13" /> Edit</a>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</x-layouts.app>
