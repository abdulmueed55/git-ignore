{{-- Maps any status keyword in the system to a consistent badge tone. --}}
@props(['value', 'label' => null])
@php
    $tone = match ($value) {
        'admitted', 'verified', 'clear', 'match', 'live', 'published', 'approved', 'sent', 'done', 'active', 'P', 'open' => 'ok',
        'selected', 'scheduled', 'processing', 'under_review', 'partial', 'L', 'review' => 'info',
        'flagged', 'waitlisted', 'pending', 'queued', 'draft', 'E', 'missing' => 'warn',
        'rejected', 'mismatch', 'failed', 'expired', 'A', 'struck_off', 'closed' => 'bad',
        'submitted' => 'brass',
        default => '',
    };
    $text = $label ?? ucwords(str_replace('_', ' ', $value));
@endphp
<span {{ $attributes->merge(['class' => 'badge'.($tone ? ' badge-'.$tone : '')]) }}>{{ $text }}</span>
