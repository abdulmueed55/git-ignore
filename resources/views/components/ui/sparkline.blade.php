{{-- Tiny inline trend line; values are any numbers. --}}
@props(['values' => [], 'width' => 120, 'height' => 32, 'tone' => 'brand'])
@php
    $vals = array_values($values);
    $n = count($vals);
    $min = $n ? min($vals) : 0;
    $max = $n ? max($vals) : 1;
    $span = ($max - $min) ?: 1;
    $pts = [];
    foreach ($vals as $i => $v) {
        $x = $n > 1 ? $i / ($n - 1) * $width : 0;
        $y = $height - 3 - (($v - $min) / $span) * ($height - 6);
        $pts[] = round($x, 1).','.round($y, 1);
    }
@endphp
@if ($n > 1)
<svg width="{{ $width }}" height="{{ $height }}" viewBox="0 0 {{ $width }} {{ $height }}" {{ $attributes }}>
    <polyline points="{{ implode(' ', $pts) }}" fill="none" stroke="var(--{{ $tone }})" stroke-width="1.6" stroke-linejoin="round" stroke-linecap="round"/>
    @php [$lx, $ly] = explode(',', end($pts)); @endphp
    <circle cx="{{ $lx }}" cy="{{ $ly }}" r="2.5" fill="var(--{{ $tone }})"/>
</svg>
@endif
