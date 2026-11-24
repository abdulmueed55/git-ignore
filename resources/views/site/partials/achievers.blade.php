@php
    $ach = collect(config('site.achievers'));
    $groups = $ach->pluck(0)->unique()->values();
    $top = $ach->max(2);
@endphp
<section class="wrap sec" id="achievements">
    <div class="sechead">
        <div>
            <span class="kicker">Achievements</span>
            <h2 class="h2" style="margin-top:4px">High achievers · {{ config('site.achievers_year') }}</h2>
            <p class="lede">Top three students of every group in the board examination · marks out of 1100</p>
        </div>
        @if ($link ?? false)<a href="{{ route('site.campus') }}" class="more">All achievements →</a>@endif
    </div>
    <div class="tiles stats">
        <div><b>{{ $top }}</b><span>highest marks · {{ number_format($top / 1100 * 100, 1) }}%</span></div>
        <div><b>{{ $ach->count() }}</b><span>high achievers in one year</span></div>
        <div><b>{{ $ach->where(2, '>=', 1000)->count() }}</b><span>students scored 1000 or more</span></div>
        <div><b>{{ $groups->count() }}</b><span>groups with top performers</span></div>
    </div>
    <div class="tabs" role="tablist" aria-label="Filter achievers by group" data-filter-tabs="#achievers-grid">
        <button type="button" role="tab" aria-selected="true" data-group="All">All</button>
        @foreach ($groups as $g)<button type="button" role="tab" aria-selected="false" data-group="{{ $g }}">{{ $g }}</button>@endforeach
    </div>
    <div class="achievers" id="achievers-grid">
        @foreach ($ach->values() as $i => [$g, $name, $marks, $photo])
            @php $rank = $ach->where(0, $g)->sortByDesc(2)->values()->search(fn ($a) => $a[1] === $name) + 1; @endphp
            <article class="ach" data-group="{{ $g }}">
                <div class="ph">
                    <img src="{{ asset('images/site/achievers/'.$photo.'.jpg') }}" alt="{{ $name }}" loading="lazy">
                    <span class="rank rank-{{ $rank }}">{{ [1 => '1st', 2 => '2nd', 3 => '3rd'][$rank] ?? $rank.'th' }}</span>
                </div>
                <div class="info">
                    <span class="g">{{ $g }}</span>
                    <span class="n">{{ $name }}</span>
                    <span class="m"><b>{{ $marks }}</b><span>/ 1100 · {{ number_format($marks / 1100 * 100, 1) }}%</span></span>
                </div>
            </article>
        @endforeach
    </div>
</section>
