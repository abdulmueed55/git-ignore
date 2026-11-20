{{-- Radial progress gauge (pure SVG, theme aware). --}}
@props(['value' => 0, 'size' => 120, 'stroke' => 10, 'tone' => 'brand', 'label' => null])
@php
    $r = ($size - $stroke) / 2;
    $c = 2 * M_PI * $r;
    $v = max(0, min(100, (float) $value));
    $off = $c * (1 - $v / 100);
@endphp
<div class="relative inline-grid place-items-center" style="width: {{ $size }}px; height: {{ $size }}px">
    <svg width="{{ $size }}" height="{{ $size }}" class="-rotate-90">
        <circle cx="{{ $size / 2 }}" cy="{{ $size / 2 }}" r="{{ $r }}" fill="none" stroke="var(--sunken)" stroke-width="{{ $stroke }}"/>
        <circle cx="{{ $size / 2 }}" cy="{{ $size / 2 }}" r="{{ $r }}" fill="none" stroke="var(--{{ $tone }})" stroke-width="{{ $stroke }}" stroke-linecap="round"
                stroke-dasharray="{{ $c }}" stroke-dashoffset="{{ $off }}" style="transition: stroke-dashoffset .8s ease"/>
    </svg>
    <div class="absolute inset-0 grid place-items-center text-center">
        <div>
            <div class="numeral text-ink" style="font-size: {{ round($size * 0.24) }}px">{{ rtrim(rtrim(number_format($v, 1), '0'), '.') }}<span class="text-[0.5em] text-muted">%</span></div>
            @if ($label)<div class="eyebrow mt-1 !text-[9.5px]">{{ $label }}</div>@endif
        </div>
    </div>
</div>
