@php $a = config('site.admission'); @endphp
<x-site.layout title="Admissions" active="Admissions" mtitle="Admissions">
    @include('site.partials.hero', ['title' => 'Admissions', 'lede' => 'Admission to HSSC Part-I and BS programmes (Fall semester) starts after the declaration of FBISE results. Dates are announced in the national press and here.', 'image' => 'images/college-building@2x.jpg', 'alt' => 'The college building', 'pos' => '45%'])

    <section class="wrap" style="padding-top:40px">
        <div class="tiles" style="grid-template-columns:repeat(auto-fit,minmax(min(100%,300px),1fr))">
            <div style="padding:24px;display:flex;flex-direction:column;gap:10px">
                <span class="kicker">{{ $cycle?->title ?? 'Admissions '.now()->year }}</span>
                <b style="font-size:20px">{{ $cycle && $cycle->isAcceptingApplications() ? 'Admissions are open — apply online.' : 'Admissions are closed right now.' }}</b>
                <span style="color:#555">{{ $cycle && $cycle->closes_at ? 'Last date: '.$cycle->closes_at->format('j M Y').'.' : 'Watch this page and the notices for the next intake.' }} 1st Year (HSSC Part-I) and BS 1st semester.</span>
            </div>
            <div style="padding:24px;display:flex;flex-wrap:wrap;gap:12px;align-items:center">
                <a href="{{ route('apply.start') }}" class="btn btn-green">Apply online</a>
                <a href="{{ route('apply.status') }}" class="btn btn-line">Check application status</a>
                <a href="{{ route('site.notices') }}" class="btn btn-line">Merit lists</a>
            </div>
        </div>
    </section>

    <section class="wrap sec" id="procedure" style="padding-top:56px">
        <h2 class="h2">How admission works</h2>
        <p class="lede" style="margin-bottom:24px">Apply online in minutes: your documents are read automatically and you get a tracking number and PIN.</p>
        <div class="steps">
            @foreach ($a['steps'] as $i => [$t, $x])<div><b class="n">0{{ $i + 1 }}</b><b class="t">{{ $t }}</b><span style="color:#4a4a4a">{{ $x }}</span></div>@endforeach
        </div>
    </section>

    <section class="band-grey"><div class="wrap sec grid cols-2">
        <div><h2 class="h2" style="font-size:30px;margin-bottom:18px">Eligibility · HSSC Part-I</h2><ul class="checklist">@foreach ($a['eligibility'] as $e)<li>{{ $e }}</li>@endforeach</ul></div>
        <div><h2 class="h2" style="font-size:30px;margin-bottom:18px">Eligibility · BS programmes</h2><ul class="checklist">@foreach ($a['bs_rules'] as $e)<li>{{ $e }}</li>@endforeach</ul></div>
    </div></section>

    <section class="band-green" id="quota"><div class="wrap sec grid cols-2" style="align-items:start">
        <div style="display:flex;flex-direction:column;gap:14px">
            <span class="kicker">FGEI Directorate policy</span>
            <h2 class="h2">Seat allocation (quota)</h2>
            <p class="lede" style="margin:0">HSSC Part-I morning shift. Students residing in Rawalpindi Cantt are given preference. Children of serving FGEI (C/G) employees are admitted over and above these seats.</p>
            <div class="quota" style="margin-top:10px">@foreach (config('site.quota') as [$l, $pct])<div class="q"><span style="font-weight:600">{{ $l }}</span><b>{{ $pct }}%</b><span class="bar"><i style="width:{{ $pct }}%"></i></span></div>@endforeach</div>
        </div>
        <div>
            <h3 style="margin:0 0 14px;font-size:22px;font-weight:800">Quota certificates to attach</h3>
            <div style="display:grid;gap:1px;background:rgba(255,255,255,.2)">
                @foreach ($a['certificates'] as [$who, $doc])<div style="background:#0a3d26;padding:14px 0;display:flex;flex-direction:column;gap:2px"><b>{{ $who }}</b><span style="opacity:.85">{{ $doc }}</span></div>@endforeach
            </div>
            <p style="margin:14px 0 0;opacity:.85">Expired certificates are not entertained in any case.</p>
        </div>
    </div></section>

    <section class="wrap sec grid cols-2" id="documents">
        <div>
            <h2 class="h2" style="font-size:30px">Required documents · HSSC-I</h2>
            <p class="lede" style="margin-bottom:18px">Attach with the admission form. Bring all originals to the interview for verification.</p>
            <ol class="numlist">@foreach ($a['documents'] as $d)<li>{{ $d }}</li>@endforeach</ol>
        </div>
        <div>
            <h2 class="h2" style="font-size:30px">Required at BS interview</h2>
            <p class="lede" style="margin-bottom:18px">Selected candidates must appear in the complete official college uniform.</p>
            <ol class="numlist">@foreach ($a['bs_documents'] as $d)<li>{{ $d }}</li>@endforeach</ol>
            <p style="margin-top:18px;color:#3d3d3d">Candidates selected on HSSC Part-I results get <b>provisional admission</b>, confirmed after HSSC Part-II results and all college requirements are met.</p>
        </div>
    </section>

    <section class="band-grey"><div class="wrap sec">
        <div class="sechead"><div><h2 class="h2">Merit lists &amp; instructions</h2><p class="lede">Published by the admission office, newest first.</p></div><a href="{{ route('site.notices') }}" class="more">All notices →</a></div>
        @if ($merit->isEmpty())
            <p class="empty">No merit lists yet for this intake. They are published here after each list is approved — <a href="{{ route('apply.status') }}">check your application status</a> with your tracking number and PIN.</p>
        @else
            <div class="table-wrap" style="background:#fff;padding:0 16px"><table class="table">
                <thead><tr><th>Document</th><th>Type</th><th>Uploaded</th><th>File</th></tr></thead>
                <tbody>@foreach ($merit as $m)<tr>
                    <td><a href="{{ route('portal.post', $m->slug) }}" style="font-weight:700">{{ $m->title }}</a></td>
                    <td>{{ $m->category ?: (\App\Models\Post::TYPES[$m->type] ?? 'Notice') }}</td>
                    <td>{{ $m->published_at?->format('j M Y') }}</td>
                    <td>@if ($m->attachment_path)<a href="{{ Storage::disk('public')->url($m->attachment_path) }}" target="_blank" rel="noopener">Download {{ strtoupper(pathinfo($m->attachment_path, PATHINFO_EXTENSION)) }}</a>@else<a href="{{ route('portal.post', $m->slug) }}">Read</a>@endif</td>
                </tr>@endforeach</tbody>
            </table></div>
        @endif
    </div></section>

    <section class="wrap sec">
        <h2 class="h2" style="margin-bottom:18px">Important rules</h2>
        <ul class="checklist" style="max-width:820px">@foreach ($a['rules'] as $r)<li>{{ $r }}</li>@endforeach</ul>
    </section>

    @include('site.partials.visit')
</x-site.layout>
