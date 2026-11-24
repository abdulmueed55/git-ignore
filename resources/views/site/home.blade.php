@php
    $kickers = ['Commitment · Devotion · Progress', 'Our students', 'Our faculty', 'FGEIs (Cantt / Garrisons) · Ministry of Defence', 'Learning', 'Sports & activities'];
    $second = [['About the college', route('site.about')], ['Campus life', route('site.campus')], ['Senior faculty', route('site.faculty')], ['Admission policy', route('site.admission').'#quota']];
    $img = fn ($p) => $p ? Storage::disk('public')->url($p) : asset('images/college-building@2x.jpg');
    $quick = [
        ['Admission procedure', route('site.admission').'#procedure', 'M9 11l3 3L22 4M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11'],
        ['Required documents', route('site.admission').'#documents', 'M14.5 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7.5L14.5 2zM14 2v6h6M16 13H8M16 17H8'],
        ['Merit lists', route('site.notices'), 'M6 9H4.5a2.5 2.5 0 0 1 0-5H6M18 9h1.5a2.5 2.5 0 0 0 0-5H18M4 22h16M18 2H6v7a6 6 0 0 0 12 0V2Z'],
        ['Admission policy', route('site.admission').'#quota', 'M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10M9 12l2 2 4-4'],
        ['Downloads', route('site.notices').'#downloads', 'M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4M7 10l5 5 5-5M12 15V3'],
        ['Check application status', route('apply.status'), 'M11 3a8 8 0 1 0 0 16 8 8 0 0 0 0-16zM21 21l-4.3-4.3'],
    ];
    $ach = collect(config('site.achievers'));
    $p = config('site.principal');
    $desc = config('site.programmes');
    $initials = fn ($n) => collect(preg_split('/\s+/', preg_replace('/^(Dr|Mrs|Mr|Hafiz|Syed)\.?\s+/', '', $n)))->filter()->map(fn ($w) => mb_substr($w, 0, 1))->take(2)->implode('');
@endphp
<x-site.layout active="Home">

    {{-- Hero slideshow --}}
    <section class="hero" aria-roledescription="carousel" aria-label="Highlights" data-hero>
        @forelse ($slides as $i => $s)
            <div class="slide {{ $i === 0 ? 'on' : '' }}" aria-roledescription="slide" aria-label="{{ $i + 1 }} of {{ $slides->count() }}">
                <img src="{{ $img($s->image_path) }}" alt="" @if ($i > 0) loading="lazy" @endif style="object-position: center {{ $i === 0 ? '45%' : '35%' }}">
                <div class="shade"></div><div class="shade2"></div>
                <div class="cap"><div class="wrap"><div class="txt">
                    <span>{{ $kickers[$i % count($kickers)] }}</span>
                    @if ($i === 0)<h1>{{ $s->heading }}</h1>@else<h2>{{ $s->heading }}</h2>@endif
                    @if ($s->subheading)<p>{{ $s->subheading }}</p>@endif
                    <div class="ctas">
                        @if ($s->cta_label && $s->cta_url)<a href="{{ $s->cta_url }}" class="btn btn-white">{{ $s->cta_label }}</a>@endif
                        @php [$l2, $h2] = $second[$i % count($second)]; @endphp
                        <a href="{{ $h2 }}" class="btn btn-outline">{{ $l2 }}</a>
                    </div>
                </div></div></div>
            </div>
        @empty
            <div class="slide on">
                <img src="{{ asset('images/college-building@2x.jpg') }}" alt="Front view of the college building">
                <div class="shade"></div>
                <div class="cap"><div class="wrap"><div class="txt">
                    <span>{{ config('site.motto') }}</span>
                    <h1>FG Liaquat Ali Degree College, Peshawar Road.</h1>
                    <div class="ctas"><a href="{{ route('apply.start') }}" class="btn btn-white">Apply for admission</a></div>
                </div></div></div>
            </div>
        @endforelse
        @if ($slides->count() > 1)
            <div class="heroctl"><div class="wrap">
                <button type="button" class="arrow" aria-label="Previous slide" data-prev>‹</button>
                <button type="button" class="arrow" aria-label="Next slide" data-next>›</button>
                @foreach ($slides as $i => $s)<button type="button" class="dot" aria-label="Show slide {{ $i + 1 }}" aria-current="{{ $i === 0 ? 'true' : 'false' }}"></button>@endforeach
            </div></div>
        @endif
    </section>

    @if ($ticker)
        <div class="ticker"><div class="wrap">
            <span class="new">New</span>
            <a href="{{ route('portal.post', $ticker->slug) }}">{{ $ticker->title }}</a>
            <time datetime="{{ $ticker->published_at?->toDateString() }}">{{ $ticker->published_at?->format('j M Y') }}</time>
        </div></div>
    @endif

    {{-- Announcements & merit lists --}}
    <section class="wrap sec" style="padding-bottom:40px">
        <div class="sechead">
            <div><h2 class="h2">Announcements &amp; merit lists</h2><p class="lede">Latest notices from the college office, newest first.</p></div>
            <a href="{{ route('site.notices') }}" class="more">All notices →</a>
        </div>
        @if ($notices->isEmpty())
            <p class="empty">No notices right now. New announcements and merit lists appear here as soon as the college office publishes them — <a href="{{ route('site.contact') }}">contact the office</a> for anything urgent.</p>
        @else
            <div class="notices">
                @foreach ($notices as $n)
                    <a href="{{ route('portal.post', $n->slug) }}" class="notice">
                        <span class="date"><b>{{ $n->published_at?->format('d') }}</b><span>{{ $n->published_at?->format('M') }}</span></span>
                        <span style="display:flex;flex-direction:column;gap:3px;min-width:0"><span class="t">{{ $n->title }}</span><span class="tag">{{ \App\Models\Post::TYPES[$n->type] ?? 'Notice' }}{{ $n->category ? ' · '.$n->category : '' }}</span></span>
                        <span class="file">{{ $n->attachment_path ? strtoupper(pathinfo($n->attachment_path, PATHINFO_EXTENSION)) : 'READ' }}</span>
                    </a>
                @endforeach
            </div>
        @endif
    </section>

    {{-- Quick links --}}
    <section class="wrap" style="padding-bottom:72px">
        <div class="tiles quick">
            @foreach ($quick as [$label, $href, $icon])
                <a href="{{ $href }}"><svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#0b6b3f" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="{{ $icon }}"/></svg>{{ $label }}</a>
            @endforeach
        </div>
    </section>

    {{-- About + timeline --}}
    <section class="band-grey"><div class="wrap sec grid cols-2" style="align-items:start">
        <div style="display:flex;flex-direction:column;gap:18px">
            <span class="kicker">About the college</span>
            <h2 class="h2">A public-sector college on Peshawar Road, Rawalpindi</h2>
            <p style="margin:0;font-size:17px;color:#3d3d3d">FG Liaquat Ali Degree College imparts intermediate and degree-level education under the Directorate of Federal Government Educational Institutions (Cantts &amp; Garrisons), Ministry of Defence — with dedicated faculty, a disciplined environment and nominal fees.</p>
            <img src="{{ asset('images/site/faculty-students-2.jpg') }}" alt="Faculty and students outside the college building" loading="lazy" style="display:block;width:100%;height:300px;object-fit:cover;object-position:center 40%;margin-top:8px">
            <a href="{{ route('site.about') }}" class="more">About &amp; history →</a>
        </div>
        <ol class="timeline">
            @foreach (config('site.timeline') as [$y, $t])<li><b>{{ $y }}</b><span>{{ $t }}</span></li>@endforeach
        </ol>
    </div></section>

    {{-- Programmes --}}
    <section class="wrap sec">
        <h2 class="h2">Programmes</h2>
        <p class="lede" style="margin-bottom:32px">HSSC in two shifts, plus four-year BS degrees affiliated with the University of the Punjab.</p>
        <div class="grid" style="grid-template-columns:repeat(auto-fit,minmax(min(100%,380px),1fr));gap:32px">
            <div class="prog">
                <h3>Intermediate (HSSC) · 2 years</h3>
                @foreach ($programmes['intermediate'] as $pr)
                    <div class="row"><b>{{ $pr->name }}</b><span>{{ $desc[$pr->code][1] ?? '' }}</span></div>
                @endforeach
                <p class="note">Morning (first) and second shift · minimum 60% in SSC for science groups.</p>
            </div>
            <div class="prog">
                <h3>BS programmes · 4 years</h3>
                @foreach ($programmes['bs'] as $pr)
                    <div class="row"><b>{{ $pr->name }}</b><span>{{ isset($desc[$pr->code][2]) ? (is_numeric($desc[$pr->code][2]) ? 'Since '.$desc[$pr->code][2] : $desc[$pr->code][2]) : '' }}</span></div>
                @endforeach
                <p class="note">Affiliated with the University of the Punjab · open to male and female students on general merit.</p>
            </div>
        </div>
        <p style="margin-top:24px"><a href="{{ route('site.programmes') }}" class="more">Subjects, eligibility and careers →</a></p>
    </section>

    {{-- Admission quota --}}
    <section class="band-green" id="quota"><div class="wrap sec grid" style="grid-template-columns:repeat(auto-fit,minmax(min(100%,420px),1fr));gap:48px;align-items:center;padding-top:72px;padding-bottom:72px">
        <div style="display:flex;flex-direction:column;gap:14px">
            <span class="kicker">Admission policy · HSSC Part-I</span>
            <h2 class="h2">How seats are allocated</h2>
            <p class="lede" style="margin:0">As notified by the FGEI Directorate (Cantt &amp; Garrisons). Students residing in Rawalpindi Cantt are given preference; children of serving FGEI employees are admitted over and above these seats.</p>
            <a href="{{ route('site.admission') }}" style="font-weight:700">Read the full admission policy →</a>
        </div>
        <div class="quota">
            @foreach (config('site.quota') as [$label, $pct])
                <div class="q"><span style="font-weight:600">{{ $label }}</span><b>{{ $pct }}%</b><span class="bar"><i style="width:{{ $pct }}%"></i></span></div>
            @endforeach
        </div>
    </div></section>

    {{-- Achievements --}}
    @include('site.partials.achievers', ['link' => true])

    {{-- Campus life --}}
    <section class="band-grey"><div class="wrap sec">
        <h2 class="h2">Campus life &amp; extra-curricular</h2>
        <p class="lede" style="margin-bottom:32px">Learning beyond the classroom — sports, debates, religious and national events, and a campus built for students.</p>
        <div class="cards">
            <figure class="feature">
                <img src="{{ asset('images/site/students-lawn.jpg') }}" alt="Students on the college lawn" loading="lazy">
                <figcaption>Student life on the college lawn</figcaption>
            </figure>
            @foreach (config('site.activities') as [$t, $x, $icon])
                <div class="card"><svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="#0b6b3f" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="{{ $icon }}"/></svg><b>{{ $t }}</b><span>{{ $x }}</span></div>
            @endforeach
        </div>
    </div></section>

    {{-- Senior faculty --}}
    <section class="wrap sec">
        <div class="sechead">
            <div style="max-width:640px">
                <span class="kicker">Faculty</span>
                <h2 class="h2" style="margin-top:4px">Senior faculty</h2>
                <p class="lede">Associate and assistant professors who head and lead the college’s departments.</p>
            </div>
            <div class="bigstats">
                <div class="bigstat"><b>{{ $faculty->count() }}</b><span>teaching faculty</span></div>
                <div class="bigstat"><b>{{ $senior->count() }}</b><span>professors</span></div>
                <div class="bigstat"><b>{{ $faculty->pluck('department_id')->filter()->unique()->count() }}</b><span>departments</span></div>
            </div>
        </div>
        @php $assoc = $senior->filter(fn ($f) => str_starts_with($f->designation, 'Associate')); $asst = $senior->filter(fn ($f) => str_starts_with($f->designation, 'Assistant')); @endphp
        @if ($assoc->isNotEmpty())
            <h3 class="lvl">Associate Professors</h3>
            <div class="seniors">
                @foreach ($assoc as $f)
                    <article class="person">
                        @if ($f->photo_path)<img src="{{ Storage::disk('public')->url($f->photo_path) }}" alt="{{ $f->name }}" loading="lazy">@else<div class="initials">{{ $initials($f->name) }}</div>@endif
                        <b>{{ $f->name }}</b><span class="ti">{{ $f->designation }}</span><span class="q">{{ $f->department?->name }} · {{ $f->qualification }}</span>
                    </article>
                @endforeach
            </div>
        @endif
        @if ($asst->isNotEmpty())
            <h3 class="lvl">Assistant Professors</h3>
            <div class="plist">
                @foreach ($asst as $f)
                    <div class="prow">
                        @if ($f->photo_path)<img src="{{ Storage::disk('public')->url($f->photo_path) }}" alt="{{ $f->name }}" loading="lazy">@else<span class="initials">{{ $initials($f->name) }}</span>@endif
                        <span><b>{{ $f->name }}</b><span>{{ $f->department?->name }} · {{ $f->qualification }}</span></span>
                    </div>
                @endforeach
            </div>
        @endif
        @if ($senior->isEmpty())
            <p class="empty">The faculty directory is being updated. <a href="{{ route('site.contact') }}">Contact the office</a> to reach a department.</p>
        @endif
        <a href="{{ route('site.faculty') }}" class="more" style="display:inline-block;margin-top:24px">Full faculty directory (all {{ $faculty->count() }} teachers) →</a>
    </section>

    {{-- Principal --}}
    <section class="band-green"><div class="wrap sec principal" style="padding-top:72px;padding-bottom:72px">
        <img src="{{ asset($p['photo']) }}" alt="{{ $p['name'] }}, Principal" loading="lazy">
        <div class="body">
            <span class="kicker">Message from the Principal</span>
            <blockquote>“{{ $p['quote'] }}”</blockquote>
            <span style="font-weight:700">{{ $p['name'] }} <span style="font-weight:400;opacity:.85">· Principal</span></span>
            <a href="{{ route('site.about') }}#principal" style="font-weight:700">Read the full message →</a>
        </div>
    </div></section>

    {{-- Vision, mission, documents --}}
    <section class="wrap sec grid cols-3" style="padding-top:72px;padding-bottom:72px">
        <div class="vline"><h2>Vision</h2><p>To forge strong, positive connections with students so they can achieve independence, build confidence and gain academic knowledge.</p></div>
        <div class="vline"><h2>Mission</h2><p>Purposeful, quality education for the children of Armed Forces personnel and civilians in cantonments, in a healthy environment that builds good citizens.</p></div>
        <div class="vline"><h2>Documents for HSSC admission</h2><p>Attested marks sheet, three blue-background photographs, character certificate, CNIC / B-Form of the candidate and father, and quota certificates where applicable. <a href="{{ route('site.admission') }}#documents">Full list →</a></p></div>
    </section>

    @include('site.partials.visit')
</x-site.layout>
