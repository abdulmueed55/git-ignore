{{-- Inner-page banner: photo (or plain green) with breadcrumb, title and one line. --}}
<section class="phero {{ empty($image) ? 'plain' : '' }}">
    @if (! empty($image))<img src="{{ asset($image) }}" alt="{{ $alt ?? '' }}" style="object-position: center {{ $pos ?? '30%' }}">@endif
    <div class="in"><div class="wrap">
        <nav aria-label="Breadcrumb" class="crumb"><a href="{{ route('portal.home') }}">Home</a> <span aria-hidden="true">›</span> {{ $crumb ?? $title }}</nav>
        <h1>{{ $title }}</h1>
        @if (! empty($lede))<p>{{ $lede }}</p>@endif
    </div></div>
</section>
