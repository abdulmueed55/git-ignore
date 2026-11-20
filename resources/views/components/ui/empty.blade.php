{{-- Empty state that always tells the user what to do next. --}}
@props(['title', 'description' => null, 'icon' => 'inbox'])
<div {{ $attributes->merge(['class' => 'flex flex-col items-center justify-center px-6 py-14 text-center']) }}>
    <div class="relative mb-4">
        <div class="hairline-grid absolute -inset-6 rounded-full opacity-60 [mask-image:radial-gradient(circle,black,transparent_70%)]"></div>
        <div class="relative grid h-12 w-12 place-items-center rounded-xl border border-line bg-surface text-brass"><x-icon :name="$icon" :size="22" /></div>
    </div>
    <div class="text-[14.5px] font-semibold text-ink">{{ $title }}</div>
    @if ($description)<p class="mt-1 max-w-sm text-[13px] text-muted">{{ $description }}</p>@endif
    @if (trim($slot))<div class="mt-4 flex flex-wrap justify-center gap-2">{{ $slot }}</div>@endif
</div>
