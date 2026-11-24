@php
    $s = \App\Models\Setting::publicValues();
    $phones = array_filter([$s['phone'] ?? config('site.phones.0'), $s['phone_admissions'] ?? config('site.phones.1')]);
    $tel = fn ($n) => 'tel:+92'.ltrim(preg_replace('/\D/', '', (string) $n), '0');
@endphp
<x-site.layout title="Contact" active="Contact" mtitle="Contact">
    @include('site.partials.hero', ['title' => 'Contact us', 'crumb' => 'Contact', 'lede' => 'Admission office, principal’s office and general enquiries.'])

    <section class="wrap sec grid cols-2" style="align-items:start">
        <div style="display:flex;flex-direction:column;gap:22px">
            <div class="vline"><h2>Address</h2><p>{{ $s['college_name'] ?? config('site.name') }} for Boys<br>{{ $s['address'] ?? 'Peshawar Road, Rawalpindi' }}</p></div>
            <div class="vline"><h2>Phone</h2><p>@foreach ($phones as $p)<a href="{{ $tel($p) }}" style="font-weight:700">{{ $p }}</a><br>@endforeach</p></div>
            <div class="vline"><h2>Email</h2><p><a href="mailto:{{ $s['email'] ?? config('site.email') }}" style="font-weight:700">{{ $s['email'] ?? config('site.email') }}</a></p></div>
            <div class="vline"><h2>Office hours</h2><p>{{ $s['office_hours'] ?? config('site.hours') }}</p></div>
            <a href="{{ $s['map_url'] ?? config('site.map') }}" class="mapbox" target="_blank" rel="noopener">Open in Google Maps →</a>
        </div>

        <div>
            <h2 class="h2" style="font-size:32px;margin-bottom:8px">Send a message</h2>
            <p class="lede" style="margin-bottom:22px">The college office replies within two working days. For your application, use <a href="{{ route('apply.status') }}">Check status</a> with your tracking number and PIN.</p>
            @if (session('sent'))<div class="flash" role="status">{{ session('sent') }}</div>@endif
            <form method="POST" action="{{ route('site.contact.store') }}" class="form" novalidate>
                @csrf
                <label>Your name <span class="req">*</span>
                    <input name="name" value="{{ old('name') }}" required maxlength="120" autocomplete="name">
                    @error('name')<span class="err">{{ $message }}</span>@enderror
                </label>
                <label>Email
                    <input name="email" type="email" value="{{ old('email') }}" maxlength="160" autocomplete="email">
                    @error('email')<span class="err">{{ $message }}</span>@enderror
                </label>
                <label>Phone
                    <input name="phone" type="tel" value="{{ old('phone') }}" maxlength="32" autocomplete="tel" placeholder="0300-1234567">
                    @error('phone')<span class="err">{{ $message }}</span>@enderror
                </label>
                <label>Subject <span class="req">*</span>
                    <input name="subject" value="{{ old('subject') }}" required maxlength="160" placeholder="e.g. BS Computer Science eligibility">
                    @error('subject')<span class="err">{{ $message }}</span>@enderror
                </label>
                <label>Message <span class="req">*</span>
                    <textarea name="message" required maxlength="3000">{{ old('message') }}</textarea>
                    @error('message')<span class="err">{{ $message }}</span>@enderror
                </label>
                <div class="hp" aria-hidden="true"><label>Website <input name="website" tabindex="-1" autocomplete="off"></label></div>
                <p style="margin:0;font-size:14px;color:#555">Give an email or a phone number so we can reply.</p>
                <button class="btn btn-green" style="justify-self:start">Send message</button>
            </form>
        </div>
    </section>
</x-site.layout>
