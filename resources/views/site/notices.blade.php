@php $types = ['' => 'All', 'notice' => 'Notices', 'announcement' => 'Announcements', 'news' => 'News']; @endphp
<x-site.layout title="Notices & downloads" active="Notices" mtitle="Notices">
    @include('site.partials.hero', ['title' => 'Notices & downloads', 'crumb' => 'Notices', 'lede' => 'Merit lists, date sheets, announcements and forms from the college office.'])

    <section class="wrap sec" style="padding-top:48px">
        <div class="tabs" role="navigation" aria-label="Filter notices">
            @foreach ($types as $k => $l)
                <a href="{{ route('site.notices', array_filter(['type' => $k])) }}" class="btn {{ ($type ?? '') === $k ? 'btn-green' : 'btn-line' }}" style="min-height:44px" @if (($type ?? '') === $k) aria-current="page" @endif>{{ $l }}</a>
            @endforeach
        </div>
        @if ($posts->isEmpty())
            <p class="empty">Nothing published here yet. New notices and merit lists appear as soon as the college office publishes them.</p>
        @else
            <div class="notices" style="grid-template-columns:1fr">
                @foreach ($posts as $n)
                    <a href="{{ route('portal.post', $n->slug) }}" class="notice">
                        <span class="date"><b>{{ $n->published_at?->format('d') }}</b><span>{{ $n->published_at?->format('M') }}</span></span>
                        <span style="display:flex;flex-direction:column;gap:3px;min-width:0">
                            <span class="t">@if ($n->is_pinned)<span style="color:#b3261e">Pinned · </span>@endif{{ $n->title }}</span>
                            <span class="tag">{{ \App\Models\Post::TYPES[$n->type] ?? 'Notice' }}{{ $n->category ? ' · '.$n->category : '' }} · {{ $n->published_at?->format('j M Y') }}</span>
                            @if ($n->excerpt)<span style="color:#444;font-size:15px">{{ \Illuminate\Support\Str::limit($n->excerpt, 160) }}</span>@endif
                        </span>
                        <span class="file">{{ $n->attachment_path ? strtoupper(pathinfo($n->attachment_path, PATHINFO_EXTENSION)) : 'READ' }}</span>
                    </a>
                @endforeach
            </div>
            <div style="margin-top:24px;display:flex;gap:12px;justify-content:space-between;flex-wrap:wrap">
                @if ($posts->previousPageUrl())<a href="{{ $posts->previousPageUrl() }}" class="btn btn-line">← Newer</a>@else<span></span>@endif
                <span style="align-self:center;color:#555">Page {{ $posts->currentPage() }} of {{ $posts->lastPage() }}</span>
                @if ($posts->nextPageUrl())<a href="{{ $posts->nextPageUrl() }}" class="btn btn-line">Older →</a>@endif
            </div>
        @endif
    </section>

    <section class="band-grey"><div class="wrap sec">
        <h2 class="h2">Upcoming events</h2>
        <p class="lede" style="margin-bottom:24px">Ceremonies, competitions and college days.</p>
        @if ($events->isEmpty())
            <p class="empty">No events scheduled right now — new events are announced here and on the notice board.</p>
        @else
            <div class="cards">
                @foreach ($events as $e)
                    <a href="{{ route('portal.post', $e->slug) }}" class="card" style="text-decoration:none;color:#1a1a1a">
                        <span class="kicker">{{ $e->event_starts_at?->format('D j M Y, g:i A') }}</span>
                        <b>{{ $e->title }}</b>
                        <span>{{ $e->venue ? $e->venue.' · ' : '' }}{{ \Illuminate\Support\Str::limit($e->excerpt, 120) }}</span>
                    </a>
                @endforeach
            </div>
        @endif
    </div></section>

    <section class="wrap sec" id="downloads">
        <h2 class="h2">Downloads</h2>
        <p class="lede" style="margin-bottom:24px">Prospectus, forms, date sheets and syllabi.</p>
        @forelse ($downloads as $cat => $files)
            <h3 class="lvl" style="margin-top:24px">{{ \App\Models\Download::CATEGORIES[$cat] ?? ucfirst($cat) }}</h3>
            <div class="plist">
                @foreach ($files as $f)
                    <a href="{{ route('portal.download', $f) }}" class="prow" style="text-decoration:none;color:#1a1a1a">
                        <span class="initials" style="width:48px;height:56px;font-size:12px">{{ strtoupper(pathinfo($f->file_name, PATHINFO_EXTENSION)) }}</span>
                        <span><b>{{ $f->title }}</b><span>{{ $f->size ? number_format($f->size / 1024).' KB' : '' }}</span></span>
                    </a>
                @endforeach
            </div>
        @empty
            <p class="empty">No downloads yet. Forms and the prospectus will be posted here; the admission office can also send them on request.</p>
        @endforelse
        <p style="margin-top:28px">Questions about a notice? <a href="{{ route('site.contact') }}">Contact the college office →</a></p>
    </section>
</x-site.layout>
