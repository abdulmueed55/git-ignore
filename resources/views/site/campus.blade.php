<x-site.layout title="Campus life & achievements" active="Campus life" mtitle="Campus life">
    @include('site.partials.hero', ['title' => 'Campus life & achievements', 'crumb' => 'Campus life', 'lede' => 'Results we are proud of, and life beyond the classroom.', 'image' => 'images/site/students-lawn.jpg', 'alt' => 'Students on the college lawn'])

    @include('site.partials.achievers')

    <section class="band-grey"><div class="wrap sec">
        <h2 class="h2">Activities</h2>
        <p class="lede" style="margin-bottom:28px">Sport, speech, faith and national pride — part of every student’s year.</p>
        <div class="cards">
            @foreach (config('site.activities') as [$t, $x, $icon])
                <div class="card"><svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="#0b6b3f" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="{{ $icon }}"/></svg><b>{{ $t }}</b><span>{{ $x }}</span></div>
            @endforeach
        </div>
    </div></section>

    <section class="wrap sec" id="gallery">
        <h2 class="h2">Gallery</h2>
        <p class="lede" style="margin-bottom:24px">Photos from campus events, added by the college from the Website Studio.</p>
        @forelse ($albums as $album)
            <h3 style="margin:28px 0 12px;font-size:22px;font-weight:800">{{ $album->title }} <span style="font-size:14px;font-weight:600;color:#555">{{ $album->taken_on?->format('M Y') }}</span></h3>
            @if ($album->description)<p style="margin:0 0 14px;color:#555">{{ $album->description }}</p>@endif
            <div class="gallery">
                @foreach ($album->images->take(8) as $im)
                    <figure><img src="{{ Storage::disk('public')->url($im->path) }}" alt="{{ $im->caption ?: $album->title }}" loading="lazy">@if ($im->caption)<figcaption>{{ $im->caption }}</figcaption>@endif</figure>
                @endforeach
            </div>
        @empty
            <p class="empty">No photo albums yet. The college adds event photos here — follow the <a href="{{ route('site.notices') }}">notices</a> for upcoming events.</p>
        @endforelse
    </section>
</x-site.layout>
