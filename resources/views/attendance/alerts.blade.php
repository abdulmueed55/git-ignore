<x-layouts.app title="Guardian alerts">
    <x-ui.page-header eyebrow="Attendance" title="Guardian alerts"
        description="SMS messages to parents, written in English and Roman Urdu. Absences, short attendance and consecutive absences are queued automatically when registers are saved.">
        <x-slot:actions>
            <form method="POST" action="{{ route('attendance.alerts.send') }}">
                @csrf
                <button class="btn btn-brass" @disabled(! ($counts['queued'] ?? 0))><x-icon name="bell" :size="15" /> Send {{ $counts['queued'] ?? 0 }} queued</button>
            </form>
        </x-slot:actions>
    </x-ui.page-header>

    <div class="mb-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-ui.stat label="In the outbox" :value="$counts['queued'] ?? 0" icon="inbox" hint="waiting to be sent" />
        <x-ui.stat label="Sent today" :value="$sentToday" icon="check" :hint="number_format($counts['sent'] ?? 0).' sent in total'" />
        <x-ui.stat label="Failed" :value="$counts['failed'] ?? 0" icon="alert" hint="usually a wrong number" />
        <div class="panel p-5">
            <div class="eyebrow">Last 30 days</div>
            <div class="mt-3 space-y-1.5 text-[12.5px]">
                @foreach ($kinds as $k => $label)
                    <div class="flex justify-between"><span class="text-muted">{{ $label }}</span><span class="mono font-semibold text-ink">{{ $byKind[$k] ?? 0 }}</span></div>
                @endforeach
            </div>
            <div class="mt-3 border-t border-line pt-2 text-[11px] text-muted">Gateway: <span class="mono text-ink">{{ $driver }}</span></div>
        </div>
    </div>

    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <div class="tabs" role="tablist">
            @foreach (['queued' => 'Outbox', 'sent' => 'Sent', 'failed' => 'Failed', 'all' => 'All'] as $k => $label)
                <a role="tab" href="{{ route('attendance.alerts', array_filter(['status' => $k, 'kind' => $kind])) }}" aria-selected="{{ $status === $k ? 'true' : 'false' }}" class="tab">{{ $label }}
                    @if ($k !== 'all')<span class="mono text-[11px] text-muted">{{ $counts[$k] ?? 0 }}</span>@endif
                </a>
            @endforeach
        </div>
        <div class="flex flex-wrap gap-1.5">
            <a href="{{ route('attendance.alerts', ['status' => $status]) }}" class="badge badge-plain {{ ! $kind ? '!bg-ink !text-paper' : '' }}">All types</a>
            @foreach ($kinds as $k => $label)
                <a href="{{ route('attendance.alerts', ['status' => $status, 'kind' => $k]) }}" class="badge badge-plain {{ $kind === $k ? '!bg-ink !text-paper' : '' }}">{{ $label }}</a>
            @endforeach
        </div>
    </div>

    <div class="panel overflow-hidden">
        @if ($alerts->isEmpty())
            <x-ui.empty icon="bell" :title="$status === 'queued' ? 'The outbox is empty' : 'No alerts match'"
                        description="New alerts appear here as soon as a teacher marks a student absent or a student falls below the attendance requirement.">
                <a href="{{ route('attendance.defaulters') }}" class="btn btn-ghost">Review defaulters</a>
            </x-ui.empty>
        @else
            <ul class="divide-y divide-line">
                @foreach ($alerts as $a)
                    <li class="grid gap-3 px-4 py-3.5 md:grid-cols-[240px_1fr_150px] md:items-start">
                        <div class="flex items-center gap-2.5">
                            <x-ui.avatar :name="$a->student->name" :size="32" />
                            <div class="min-w-0 leading-tight">
                                <a href="{{ route('attendance.student', $a->student) }}" class="block truncate font-semibold text-ink hover:text-brand">{{ $a->student->name }}</a>
                                <div class="truncate text-[11.5px] text-muted">{{ $a->student->section?->label() }}</div>
                                <div class="mono text-[11px] text-muted">{{ $a->to }}</div>
                            </div>
                        </div>
                        <div class="min-w-0">
                            <span class="badge {{ ['absent_today' => 'badge-warn', 'short_attendance' => 'badge-bad', 'consecutive_absence' => 'badge-brass'][$a->kind] ?? '' }} mb-1.5">{{ $kinds[$a->kind] ?? $a->kind }}</span>
                            <p class="rounded-lg rounded-tl-none border border-line bg-sunken/50 px-3 py-2 text-[12.5px] leading-relaxed text-ink-2">{{ $a->message }}</p>
                        </div>
                        <div class="flex items-center justify-between gap-2 md:flex-col md:items-end">
                            <x-ui.status :value="$a->status" />
                            <span class="mono text-[11px] text-muted">{{ ($a->sent_at ?? $a->created_at)->format('j M, H:i') }}</span>
                        </div>
                    </li>
                @endforeach
            </ul>
            <div class="border-t border-line px-4 py-3">{{ $alerts->links() }}</div>
        @endif
    </div>
</x-layouts.app>
