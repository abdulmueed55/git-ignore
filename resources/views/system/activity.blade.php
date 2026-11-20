@php use App\Http\Controllers\Academics\ActivityController as AC; @endphp
<x-layouts.app title="Activity log">
    <x-ui.page-header eyebrow="Administration" title="Activity log" description="An audit trail of who changed what — admissions decisions, attendance, records and publishing." />

    <div class="mb-6 grid gap-3 sm:grid-cols-3">
        <x-ui.stat label="Today" :value="$todayCount" icon="clock" hint="actions recorded" />
        <x-ui.stat label="Last 7 days" :value="$weekCount" icon="list" />
        <div class="panel flex flex-col justify-between p-5">
            <div class="eyebrow">14-day trend</div>
            <x-ui.sparkline :values="$spark" :width="260" :height="44" tone="brass" class="mt-3 w-full" />
        </div>
    </div>

    <div class="grid gap-6 lg:grid-cols-[260px_1fr]">
        <form method="GET" class="panel h-max space-y-4 p-5">
            <div class="eyebrow">Filter</div>
            <x-ui.field name="module" type="select" label="Module" :value="request('module')" :options="$modules" empty="All modules" />
            <x-ui.field name="user" type="select" label="Person" :value="request('user')" :options="$users" empty="Everyone" />
            <x-ui.field name="from" type="date" label="From" :value="request('from')" />
            <x-ui.field name="to" type="date" label="To" :value="request('to')" />
            <div class="flex gap-2">
                <button class="btn btn-primary flex-1"><x-icon name="filter" :size="15" /> Apply</button>
                <a href="{{ route('system.activity') }}" class="btn btn-ghost">Reset</a>
            </div>
        </form>

        <section class="panel">
            @forelse ($days as $day => $entries)
                @php $d = \Illuminate\Support\Carbon::parse($day); @endphp
                <div class="sticky top-[60px] z-10 flex items-baseline gap-3 border-b border-line bg-surface/95 px-5 py-3 backdrop-blur">
                    <span class="display text-[17px] text-ink">{{ $d->isToday() ? 'Today' : ($d->isYesterday() ? 'Yesterday' : $d->format('l')) }}</span>
                    <span class="text-[12px] text-muted">{{ $d->format('j F Y') }}</span>
                    <span class="mono ml-auto text-[11px] text-muted">{{ $entries->count() }}</span>
                </div>
                <ol class="relative px-5 py-2">
                    <span class="absolute bottom-4 left-[38px] top-4 w-px bg-line"></span>
                    @foreach ($entries as $log)
                        @php
                            $module = \Illuminate\Support\Str::before($log->action, '.');
                            $tone = match ($module) { 'admissions' => 'info', 'attendance' => 'ok', 'studio' => 'brass', 'staff' => 'warn', 'auth' => '', default => 'brand' };
                            $url = AC::subjectUrl($log);
                        @endphp
                        <li class="relative flex gap-4 py-2.5">
                            <x-ui.avatar :name="$log->user?->name ?? 'System'" :size="36" class="relative z-[1] ring-4 ring-surface" />
                            <div class="min-w-0 flex-1">
                                <div class="text-[13.5px] text-ink-2">
                                    <span class="font-semibold text-ink">{{ $log->user?->name ?? 'System' }}</span>
                                    @if ($url)<a href="{{ $url }}" class="hover:text-brand hover:underline">{{ lcfirst($log->description) }}</a>@else{{ lcfirst($log->description) }}@endif
                                </div>
                                <div class="mt-0.5 flex flex-wrap items-center gap-2 text-[11.5px] text-muted">
                                    <span class="mono">{{ $log->created_at->format('H:i') }}</span>
                                    <span class="badge badge-plain {{ $tone ? 'badge-'.$tone : '' }} !h-[18px] !text-[10.5px]">{{ AC::MODULES[$module] ?? ucfirst($module) }}</span>
                                    @if ($log->ip)<span class="mono">{{ $log->ip }}</span>@endif
                                    @if (! empty($log->properties['changed']))<span>changed {{ implode(', ', array_map(fn ($f) => str_replace('_', ' ', $f), $log->properties['changed'])) }}</span>@endif
                                </div>
                            </div>
                        </li>
                    @endforeach
                </ol>
            @empty
                <x-ui.empty icon="list" title="No activity for these filters" description="Every sign-in, admission decision, attendance save and website publish is recorded here. Widen the date range or reset the filters.">
                    <a href="{{ route('system.activity') }}" class="btn btn-ghost">Reset filters</a>
                </x-ui.empty>
            @endforelse
            @if ($logs->hasPages())<div class="border-t border-line px-5 py-3">{{ $logs->links() }}</div>@endif
        </section>
    </div>
</x-layouts.app>
