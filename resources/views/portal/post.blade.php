<x-portal.layout :title="$post->title" :description="$post->excerpt" :menu="$menu" :s="$s">
    <article class="mx-auto max-w-3xl px-4 pt-10">
        <a href="{{ route('portal.home') }}" class="inline-flex items-center gap-1 text-[12.5px] text-muted hover:text-ink"><x-icon name="arrow-left" :size="14" /> Home</a>
        <div class="mt-6 flex flex-wrap items-center gap-2 text-[12px]">
            <span class="eyebrow !text-brass">{{ $post->typeLabel() }}</span><span class="text-muted">· {{ $post->category }} · {{ $post->published_at->format('l, j F Y') }} · {{ $post->readingMinutes() }} min read</span>
        </div>
        <h1 class="display mt-3 text-[32px] leading-[1.12] text-ink sm:text-[40px]">{{ $post->title }}</h1>
        <p class="mt-4 text-[16px] leading-relaxed text-ink-2">{{ $post->excerpt }}</p>

        @if ($post->type === 'event' && $post->event_starts_at)
            <div class="mt-6 grid gap-px overflow-hidden rounded-xl border border-line bg-line sm:grid-cols-2">
                <div class="flex items-center gap-3 bg-surface px-5 py-4"><x-icon name="calendar" class="text-brass" /><div><div class="eyebrow">When</div><div class="text-[14px] font-semibold text-ink">{{ $post->event_starts_at->format('l j F Y') }}</div><div class="text-[12.5px] text-muted">{{ $post->event_starts_at->format('g:i A') }}@if ($post->event_ends_at) – {{ $post->event_ends_at->format('g:i A') }}@endif</div></div></div>
                <div class="flex items-center gap-3 bg-surface px-5 py-4"><x-icon name="map" class="text-brass" /><div><div class="eyebrow">Where</div><div class="text-[14px] font-semibold text-ink">{{ $post->venue }}</div></div></div>
            </div>
        @endif

        @if ($post->coverUrl())<img src="{{ $post->coverUrl() }}" alt="" class="mt-8 aspect-video w-full rounded-xl object-cover">@endif

        <div class="prose-ledger mt-8 !text-[15.5px]">{!! $post->body !!}</div>

        @if ($post->attachment_path)
            <a href="{{ $post->attachmentUrl() }}" target="_blank" class="mt-8 flex items-center gap-4 rounded-xl border border-line bg-surface p-4 hover:border-brand">
                <span class="grid h-11 w-11 place-items-center rounded-lg bg-bad-soft text-[11px] font-bold text-bad">PDF</span>
                <span class="flex-1"><span class="block text-[14px] font-semibold text-ink">{{ $post->attachment_name }}</span><span class="block text-[12px] text-muted">Download or print the official copy</span></span>
                <x-icon name="download" class="text-brand" />
            </a>
        @endif
    </article>

    @if ($related->isNotEmpty())
        <section class="mx-auto mt-16 max-w-3xl px-4">
            <h2 class="eyebrow border-b border-line pb-3">More {{ \Illuminate\Support\Str::plural(strtolower($post->typeLabel())) }}</h2>
            <ul class="divide-y divide-line">
                @foreach ($related as $r)
                    <li><a href="{{ route('portal.post', $r->slug) }}" class="flex items-baseline justify-between gap-4 py-3 hover:text-brand"><span class="text-[14px] font-medium text-ink">{{ $r->title }}</span><span class="shrink-0 text-[12px] text-muted">{{ $r->published_at->format('j M') }}</span></a></li>
                @endforeach
            </ul>
        </section>
    @endif
</x-portal.layout>
