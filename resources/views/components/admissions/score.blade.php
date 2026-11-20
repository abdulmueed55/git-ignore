{{-- Verification score bar: 0-100 with status tone. --}}
@props(['score' => null, 'status' => 'pending', 'wide' => false])
@php
    $tone = match ($status) { 'clear' => 'ok', 'review' => 'warn', 'mismatch' => 'bad', default => 'muted' };
@endphp
<div {{ $attributes->merge(['class' => 'flex items-center gap-2']) }} title="Document consistency {{ $score ?? '—' }}%">
    <div class="h-1.5 {{ $wide ? 'w-full' : 'w-16' }} overflow-hidden rounded-full bg-sunken">
        <div class="h-full rounded-full" style="width: {{ (int) ($score ?? 0) }}%; background: var(--{{ $tone }})"></div>
    </div>
    <span class="mono text-[11.5px] {{ $score === null ? 'text-muted' : 'text-ink-2' }}">{{ $score !== null ? $score : '—' }}</span>
</div>
