@props(['name' => '', 'size' => 32])
@php
    $parts = preg_split('/\s+/', trim(preg_replace('/^((Prof|Dr|Mr|Ms|Mrs|Hafiz)\.?\s+)+/i', '', $name)));
    $ini = strtoupper(mb_substr($parts[0] ?? '', 0, 1).mb_substr(count($parts) > 1 ? end($parts) : '', 0, 1));
    $hues = ['brand', 'brass', 'info', 'ok', 'warn'];
    $tone = $hues[crc32($name) % count($hues)];
@endphp
<span {{ $attributes->merge(['class' => 'inline-grid shrink-0 place-items-center rounded-full font-semibold']) }}
      style="width: {{ $size }}px; height: {{ $size }}px; font-size: {{ round($size * 0.36) }}px; background: var(--{{ $tone }}-soft, var(--brass-soft)); color: var(--{{ $tone }})">{{ $ini }}</span>
