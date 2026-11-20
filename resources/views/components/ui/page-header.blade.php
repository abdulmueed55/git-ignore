@props(['title', 'eyebrow' => null, 'description' => null])
<div class="mb-7 flex flex-wrap items-end justify-between gap-4">
    <div class="min-w-0">
        @if ($eyebrow)
            <div class="eyebrow mb-2 flex items-center gap-2"><span class="inline-block h-px w-5 bg-brass"></span>{{ $eyebrow }}</div>
        @endif
        <h1 class="display text-[28px] font-medium leading-tight text-ink sm:text-[32px]">{{ $title }}</h1>
        @if ($description)
            <p class="mt-1.5 max-w-2xl text-[13.5px] text-muted">{{ $description }}</p>
        @endif
    </div>
    @isset($actions)
        <div class="flex flex-wrap items-center gap-2">{{ $actions }}</div>
    @endisset
</div>
