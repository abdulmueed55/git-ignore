@props(['value'])
@php
    [$label, $cls, $icon] = match ($value) {
        'clear' => ['Docs match', 'badge-ok', 'shield'],
        'review' => ['Needs a look', 'badge-warn', 'eye'],
        'mismatch' => ['Mismatch', 'badge-bad', 'alert'],
        'processing' => ['Reading…', 'badge-info', 'cpu'],
        default => ['Not checked', '', 'clock'],
    };
@endphp
<span {{ $attributes->merge(['class' => "badge badge-plain {$cls}"]) }}><x-icon :name="$icon" :size="11" /> {{ $label }}</span>
