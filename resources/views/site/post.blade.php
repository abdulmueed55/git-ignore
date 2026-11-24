<x-site.layout :title="$post->title" active="Notices" mtitle="Notice" :description="$post->excerpt">
    @include('site.partials.hero', ['title' => $post->title, 'crumb' => \App\Models\Post::TYPES[$post->type] ?? 'Notice', 'lede' => ($post->category ? $post->category.' · ' : '').$post->published_at?->format('j F Y')])

    <section class="wrap sec grid" style="grid-template-columns:minmax(0,1fr);max-width:880px">
        @if ($post->cover_path)<img src="{{ Storage::disk('public')->url($post->cover_path) }}" alt="" style="width:100%;max-height:420px;object-fit:cover">@endif
        @if ($post->type === 'event' && $post->event_starts_at)
            <div class="flash">{{ $post->event_starts_at->format('l j F Y, g:i A') }}@if ($post->event_ends_at) – {{ $post->event_ends_at->format($post->event_ends_at->isSameDay($post->event_starts_at) ? 'g:i A' : 'j F, g:i A') }}@endif{{ $post->venue ? ' · '.$post->venue : '' }}</div>
        @endif
        <div class="post-body">{!! $post->body !!}</div>
        @if ($post->attachment_path)
            <p><a href="{{ Storage::disk('public')->url($post->attachment_path) }}" class="btn btn-green" target="_blank" rel="noopener">Download {{ $post->attachment_name ?: 'attachment' }}</a></p>
        @endif
        <p><a href="{{ route('site.notices') }}" class="more">← All notices</a></p>
    </section>

    @if ($related->isNotEmpty())
        <section class="band-grey"><div class="wrap sec" style="padding-top:48px;padding-bottom:48px">
            <h2 class="h2" style="font-size:26px;margin-bottom:16px">More {{ strtolower(\Illuminate\Support\Str::plural(\App\Models\Post::TYPES[$post->type] ?? 'notice')) }}</h2>
            <div class="notices" style="background:#fff;padding:0 16px">
                @foreach ($related as $n)
                    <a href="{{ route('portal.post', $n->slug) }}" class="notice"><span class="date"><b>{{ $n->published_at?->format('d') }}</b><span>{{ $n->published_at?->format('M') }}</span></span><span class="t">{{ $n->title }}</span></a>
                @endforeach
            </div>
        </div></section>
    @endif
</x-site.layout>
