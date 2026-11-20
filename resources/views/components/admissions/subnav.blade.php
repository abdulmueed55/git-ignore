{{-- Secondary navigation shared by admissions screens. --}}
@php
    $items = [
        ['admissions.dashboard', 'Desk', 'inbox', ['admissions.dashboard']],
        ['admissions.index', 'Applications', 'files', ['admissions.index', 'admissions.show']],
        ['admissions.verification', 'AI verification', 'scan', ['admissions.verification']],
        ['admissions.merit.index', 'Merit lists', 'trophy', ['admissions.merit.*']],
        ['admissions.cycles.index', 'Cycles', 'calendar', ['admissions.cycles.*']],
    ];
@endphp
<div class="tabs mb-6 print:hidden" role="tablist">
    @foreach ($items as [$route, $label, $icon, $patterns])
        <a href="{{ route($route) }}" class="tab" role="tab" aria-selected="{{ request()->routeIs(...$patterns) ? 'true' : 'false' }}">
            <x-icon :name="$icon" :size="14" /> {{ $label }}
        </a>
    @endforeach
</div>
