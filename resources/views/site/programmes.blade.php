@php $desc = config('site.programmes'); @endphp
<x-site.layout title="Programmes" active="Programmes" mtitle="Programmes">
    @include('site.partials.hero', ['title' => 'Programmes', 'lede' => 'Intermediate (HSSC) in two shifts and four-year BS degrees affiliated with the University of the Punjab.', 'image' => 'images/site/students-lawn.jpg', 'alt' => 'Students on the college lawn'])

    <section class="wrap sec" id="intermediate">
        <h2 class="h2">Intermediate (HSSC) · 2 years</h2>
        <p class="lede" style="margin-bottom:28px">Morning (first) and second shift · examinations by FBISE · admission in 1st Year (HSSC Part-I).</p>
        <div class="grid" style="grid-template-columns:repeat(auto-fit,minmax(min(100%,360px),1fr))">
            @forelse ($programmes['intermediate'] as $p)
                @php $d = $desc[$p->code] ?? [$p->eligibility, null, null, null]; @endphp
                <article class="pcard">
                    <span class="kicker">{{ $p->code }}</span>
                    <h3>{{ $p->name }}</h3>
                    @if ($d[0])<p style="margin:0;color:#3d3d3d">{{ $d[0] }}</p>@endif
                    <dl>
                        @if ($d[1])<dt>Electives</dt><dd>{{ $d[1] }}</dd>@endif
                        <dt>Eligibility</dt><dd>{{ $d[2] ?? ($p->min_percentage ? 'Minimum '.$p->min_percentage.'% in SSC' : 'SSC') }}</dd>
                        @if ($d[3])<dt>Leads to</dt><dd>{{ $d[3] }}</dd>@endif
                        @if ($p->seats)<dt>Seats</dt><dd>{{ $p->seats }} per intake</dd>@endif
                    </dl>
                </article>
            @empty
                <p class="empty">Programme details are being updated. <a href="{{ route('site.contact') }}">Contact the admission office</a>.</p>
            @endforelse
        </div>
        <p style="margin-top:20px;color:#555">Compulsory subjects in every group: English, Urdu, Islamiyat / Ethics (Part-I) and Pakistan Studies (Part-II).</p>
    </section>

    <section class="band-grey" id="bs"><div class="wrap sec">
        <h2 class="h2">BS programmes · 4 years</h2>
        <p class="lede" style="margin-bottom:28px">8 semesters · University of the Punjab · male &amp; female students · admission in the 1st semester.</p>
        <div class="grid" style="grid-template-columns:repeat(auto-fit,minmax(min(100%,360px),1fr))">
            @foreach ($programmes['bs'] as $p)
                @php
                    $d = $desc[$p->code] ?? [$p->eligibility, $p->department?->name, null, null];
                    $teachers = $faculty->where('department_id', $p->department_id)->count();
                @endphp
                <article class="pcard">
                    <span class="kicker">{{ $d[1] ?? 'BS (4-Year)' }}</span>
                    <h3>{{ $p->name }}</h3>
                    @if ($d[0])<p style="margin:0;color:#3d3d3d">{{ $d[0] }}</p>@endif
                    <dl>
                        @if ($d[2])<dt>Started</dt><dd>{{ $d[2] }}</dd>@endif
                        <dt>Seats</dt><dd>{{ $d[3] ?? ($p->seats ? $p->seats.' per intake' : 'Per merit list') }}</dd>
                        @if ($teachers)<dt>Faculty</dt><dd>{{ $teachers }} teachers</dd>@endif
                        @if ($p->min_percentage)<dt>Eligibility</dt><dd>Intermediate with at least {{ $p->min_percentage }}%</dd>@endif
                    </dl>
                    <a href="{{ route('site.admission') }}" class="more">Admission details →</a>
                </article>
            @endforeach
        </div>
    </div></section>

    <section class="band-green"><div class="wrap sec" style="display:flex;flex-wrap:wrap;gap:20px 40px;align-items:center;justify-content:space-between;padding-top:56px;padding-bottom:56px">
        <div><h2 class="h2">Ready to apply?</h2><p class="lede">Read the eligibility rules and required documents first.</p></div>
        <div style="display:flex;flex-wrap:wrap;gap:12px"><a href="{{ route('apply.start') }}" class="btn btn-white">Apply online</a><a href="{{ route('site.admission') }}" class="btn btn-outline">Admission guide</a><a href="{{ route('site.faculty') }}" class="btn btn-outline">Meet the faculty</a></div>
    </div></section>
</x-site.layout>
