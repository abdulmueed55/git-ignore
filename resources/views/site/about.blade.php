@php $p = config('site.principal'); @endphp
<x-site.layout title="About" active="About" mtitle="About">
    @include('site.partials.hero', ['title' => 'About the college', 'crumb' => 'About', 'lede' => 'One of the leading public-sector colleges of the Rawalpindi region.', 'image' => 'images/site/faculty-students.jpg', 'alt' => 'Faculty and students outside the college building', 'pos' => '25%'])

    <section class="wrap sec grid cols-2">
        <div class="prose">
            <h2 class="h2" style="font-size:32px;margin-bottom:16px">Who we are</h2>
            @foreach (config('site.about') as $para)<p>{{ $para }}</p>@endforeach
        </div>
        <div>
            <h2 class="h2" style="font-size:32px;margin-bottom:20px">Our history</h2>
            <ol class="timeline">@foreach (config('site.timeline') as [$y, $t])<li><b>{{ $y }}</b><span>{{ $t }}</span></li>@endforeach</ol>
        </div>
    </section>

    <section class="band-green" id="principal"><div class="wrap sec principal" style="padding-top:72px;padding-bottom:72px">
        <img src="{{ asset($p['photo']) }}" alt="{{ $p['name'] }}, Principal" style="width:180px;height:248px">
        <div class="body" style="font-size:17px">
            <span class="kicker">Message from the Principal</span>
            <h2 class="h2" style="font-size:30px">Welcome to FGLADC</h2>
            @foreach ($p['message'] as $para)<p style="margin:0">{{ $para }}</p>@endforeach
            <span style="font-weight:700">{{ $p['name'] }} <span style="font-weight:400;opacity:.85">· Principal, FGLADC Rawalpindi</span></span>
        </div>
    </div></section>

    <section class="wrap sec grid" style="grid-template-columns:repeat(auto-fit,minmax(min(100%,380px),1fr));gap:40px;padding-top:72px;padding-bottom:72px">
        <div class="vtop"><h2>Vision</h2><p style="font-size:17px">{{ config('site.vision') }}</p></div>
        <div class="vtop"><h2>Mission</h2><p style="font-size:17px">{{ config('site.mission') }}</p></div>
    </section>

    <section class="band-grey"><div class="wrap sec" style="padding-top:72px;padding-bottom:72px">
        <h2 class="h2" style="font-size:32px">Campus &amp; facilities</h2>
        <p class="lede" style="margin-bottom:28px">Everything a student needs, on one campus on Peshawar Road.</p>
        <div class="tiles ftiles">
            @foreach (config('site.facilities') as [$t, $x, $icon])
                <div><svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#0b6b3f" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="{{ $icon }}"/></svg><b>{{ $t }}</b><span>{{ $x }}</span></div>
            @endforeach
        </div>
    </div></section>

    <section class="wrap sec grid cols-2" style="align-items:center;padding-top:72px;padding-bottom:72px">
        <div style="display:flex;flex-direction:column;gap:14px;font-size:17px;color:#333">
            <span class="kicker">FGEIs (Cantt / Garrisons)</span>
            <h2 class="h2" style="font-size:32px">Part of a national network</h2>
            <p style="margin:0">The Federal Government Educational Institutions (Cantonments &amp; Garrisons) run schools and colleges in cantonments and garrisons across Pakistan under the Ministry of Defence. Admission policy, quotas and calendars for FGLADC follow the Directorate’s notifications.</p>
            <a href="{{ route('site.admission') }}#quota" class="more">Admission policy &amp; quota →</a>
        </div>
        <div class="tiles" style="grid-template-columns:repeat(2,minmax(0,1fr))">
            <div style="padding:24px" class="bigstat"><b>2007</b><span>established</span></div>
            <div style="padding:24px" class="bigstat"><b>{{ $faculty }}</b><span>teaching faculty</span></div>
            <div style="padding:24px" class="bigstat"><b>{{ \App\Models\Program::where('is_active', true)->where('level', 'bs')->count() }}</b><span>BS programmes</span></div>
            <div style="padding:24px" class="bigstat"><b>2</b><span>HSSC shifts</span></div>
        </div>
    </section>

    @include('site.partials.visit')
</x-site.layout>
