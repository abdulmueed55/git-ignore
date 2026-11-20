@props(['label', 'value', 'suffix' => null, 'delta' => null, 'deltaGood' => true, 'hint' => null, 'icon' => null, 'href' => null])
@php $tag = $href ? 'a' : 'div'; @endphp
<{{ $tag }} @if($href) href="{{ $href }}" @endif {{ $attributes->merge(['class' => 'panel group relative block overflow-hidden p-5 transition hover:border-line-strong']) }}>
    <div class="flex items-start justify-between">
        <div class="eyebrow">{{ $label }}</div>
        @if ($icon)
            <span class="grid h-8 w-8 place-items-center rounded-lg bg-sunken text-muted group-hover:text-brand"><x-icon :name="$icon" :size="16" /></span>
        @endif
    </div>
    <div class="mt-3 flex items-baseline gap-1.5">
        <span class="numeral text-[38px] text-ink">{{ $value }}</span>
        @if ($suffix)<span class="text-[14px] font-semibold text-muted">{{ $suffix }}</span>@endif
    </div>
    <div class="mt-2 flex items-center gap-2 text-[12px]">
        @if ($delta !== null)
            <span class="mono font-semibold {{ $deltaGood ? 'text-ok' : 'text-bad' }}">{{ $delta }}</span>
        @endif
        @if ($hint)<span class="text-muted">{{ $hint }}</span>@endif
    </div>
    {{ $slot }}
</{{ $tag }}>
