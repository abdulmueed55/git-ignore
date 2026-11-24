@props(['title' => null, 'active' => 'Home', 'mtitle' => null, 'description' => null])
@php
    $s = \App\Models\Setting::publicValues();
    $name = $s['college_name'] ?? config('site.name');
    $phone = $s['phone'] ?? config('site.phones.0');
    $phone2 = $s['phone_admissions'] ?? config('site.phones.1');
    $email = $s['email'] ?? config('site.email');
    $hours = $s['office_hours'] ?? config('site.hours');
    $tel = fn ($n) => 'tel:+92'.ltrim(preg_replace('/\D/', '', (string) $n), '0');
    $nav = [
        'About' => route('site.about'), 'Admissions' => route('site.admission'), 'Programmes' => route('site.programmes'),
        'Faculty' => route('site.faculty'), 'Campus life' => route('site.campus'), 'Notices' => route('site.notices'), 'Contact' => route('site.contact'),
    ];
    $tabs = [
        ['Home', route('portal.home'), 'M3 11l9-8 9 8M5 10v10h5v-6h4v6h5V10'],
        ['Admissions', route('site.admission'), 'M9 11l3 3L22 4M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11'],
        ['Notices', route('site.notices'), 'M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9M10.3 21a1.94 1.94 0 0 0 3.4 0'],
        ['Faculty', route('site.faculty'), 'M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8zM22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75'],
        ['Contact', route('site.contact'), 'M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6A19.79 19.79 0 0 1 2.12 4.18 2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.13.96.36 1.9.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.91.34 1.85.57 2.81.7A2 2 0 0 1 22 16.92z'],
    ];
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>{{ $title ? $title.' — ' : '' }}{{ $name }}, Rawalpindi</title>
    <meta name="description" content="{{ $description ?? 'FG Liaquat Ali Degree College, Peshawar Road, Rawalpindi — intermediate (HSSC) and BS programmes under the Federal Government Educational Institutions (Cantt / Garrisons).' }}">
    <meta name="theme-color" content="#0a3d26">
    <meta property="og:title" content="{{ $title ?? $name }}">
    <meta property="og:image" content="{{ asset('images/college-building@2x.jpg') }}">
    <link rel="icon" href="{{ asset('images/app-icon-192.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('images/app-icon-192.png') }}">
    @vite(['resources/css/site.css', 'resources/js/site.js'])
</head>
<body class="site">
<a href="#main" class="skip">Skip to content</a>

{{-- Desktop header --}}
<div class="topstrip">
    <div class="wrap">
        <span class="op">{{ config('site.authority') }}</span>
        <span class="links">
            <a href="{{ $tel($phone) }}">{{ $phone }}</a>
            @if ($phone2)<a href="{{ $tel($phone2) }}">{{ $phone2 }}</a>@endif
            <a href="mailto:{{ $email }}">{{ $email }}</a>
            <span class="op">{{ $hours }}</span>
            <a href="{{ route('student.login') }}" class="portal">Student portal</a>
            <a href="{{ route('login') }}" class="portal">Staff portal</a>
        </span>
    </div>
</div>
<header class="mainhead">
    <div class="wrap">
        <a href="{{ route('portal.home') }}" class="brand">
            <img src="{{ asset('images/crest.jpg') }}" alt="FG Liaquat Ali Degree College crest">
            <span><b>{{ $name }}</b><span>{{ config('site.tagline') }}</span></span>
        </a>
        <nav class="mainnav" aria-label="Main">
            @foreach ($nav as $label => $href)
                <a href="{{ $href }}" @if ($active === $label) aria-current="page" @endif>{{ $label }}</a>
            @endforeach
            <a href="{{ route('apply.start') }}" class="apply">Apply online</a>
        </nav>
    </div>
</header>

{{-- Phone: app-style top bar --}}
<header class="mtop">
    @if ($mtitle)
        <a href="{{ route('portal.home') }}" class="iconbtn" aria-label="Back to home"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 18l-6-6 6-6"/></svg></a>
        <span class="mtitle">{{ $mtitle }}</span>
    @else
        <a href="{{ route('portal.home') }}" class="mbrand">
            <img src="{{ asset('images/crest.jpg') }}" alt="">
            <span style="min-width:0"><b>{{ $name }}</b><small>Peshawar Road, Rawalpindi</small></span>
        </a>
    @endif
    <button type="button" class="iconbtn" aria-label="Open menu" data-menu-open><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M4 7h16M4 12h16M4 17h16"/></svg></button>
</header>
<div class="mmenu" id="mmenu" role="dialog" aria-modal="true" aria-label="Menu" hidden>
    <div class="bar">
        <img src="{{ asset('images/crest.jpg') }}" alt="">
        <span style="flex:1;font-weight:800">Menu</span>
        <button type="button" class="iconbtn" aria-label="Close menu" data-menu-close style="color:#fff"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18"/></svg></button>
    </div>
    <nav aria-label="Mobile">
        <a href="{{ route('portal.home') }}">Home<span aria-hidden="true">›</span></a>
        @foreach ($nav as $label => $href)
            <a href="{{ $href }}">{{ $label === 'Notices' ? 'Notices & merit lists' : $label }}<span aria-hidden="true">›</span></a>
        @endforeach
        <a href="{{ route('apply.status') }}">Admission status<span aria-hidden="true">›</span></a>
        <a href="{{ route('student.login') }}">Student portal<span aria-hidden="true">›</span></a>
        <a href="{{ route('login') }}">Staff portal<span aria-hidden="true">›</span></a>
    </nav>
    <div class="actions">
        <a href="{{ route('apply.start') }}" class="light">Apply online</a>
        <a href="{{ $tel($phone) }}" class="outline">Call {{ $phone }}</a>
    </div>
</div>

<main id="main">
    {{ $slot }}
</main>

<footer class="foot">
    <div class="wrap cols">
        <div>
            <img src="{{ asset('images/crest.jpg') }}" alt="">
            <b style="font-size:17px">{{ $name }}</b>
            <span>{{ $s['address'] ?? 'Peshawar Road, Rawalpindi' }}</span>
            <span>{{ config('site.motto') }}</span>
        </div>
        <div>
            <b>Admissions</b>
            <a href="{{ route('apply.start') }}">Apply online</a>
            <a href="{{ route('site.admission') }}#procedure">Procedure &amp; policy</a>
            <a href="{{ route('site.notices') }}">Merit lists</a>
            <a href="{{ route('apply.status') }}">Check application status</a>
        </div>
        <div>
            <b>College</b>
            <a href="{{ route('site.about') }}">About &amp; history</a>
            <a href="{{ route('site.faculty') }}">Faculty</a>
            <a href="{{ route('site.campus') }}">Campus life &amp; achievements</a>
            <a href="{{ route('site.notices') }}#downloads">Notices &amp; downloads</a>
        </div>
        <div>
            <b>Contact</b>
            <a href="{{ $tel($phone) }}">{{ $phone }}</a>
            @if ($phone2)<a href="{{ $tel($phone2) }}">{{ $phone2 }}</a>@endif
            <a href="mailto:{{ $email }}">{{ $email }}</a>
            <span>{{ $hours }}</span>
            <span style="display:flex;gap:14px;flex-wrap:wrap">
                @foreach (['facebook_url' => 'Facebook', 'instagram_url' => 'Instagram', 'youtube_url' => 'YouTube', 'x_url' => 'X'] as $k => $l)
                    @if (! empty($s[$k]))<a href="{{ $s[$k] }}" rel="noopener" target="_blank">{{ $l }}</a>@endif
                @endforeach
            </span>
        </div>
    </div>
    <div class="wrap base">
        <span>© {{ now()->year }} {{ $name }}, Peshawar Road, Rawalpindi</span>
        <span>Under the Directorate of FGEIs (Cantt / Garrisons), Ministry of Defence</span>
    </div>
</footer>

{{-- Phone: bottom tab bar --}}
<nav class="mtab" aria-label="Primary">
    @foreach ($tabs as [$label, $href, $icon])
        <a href="{{ $href }}" @if ($active === $label) aria-current="page" @endif>
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="{{ $active === $label ? '2.2' : '1.7' }}" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="{{ $icon }}"/></svg>
            {{ $label }}
        </a>
    @endforeach
</nav>
</body>
</html>
