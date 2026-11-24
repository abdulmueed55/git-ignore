@php
    $s = \App\Models\Setting::publicValues();
    $phones = array_unique(array_filter([$s['phone'] ?? config('site.phones.0'), $s['phone_admissions'] ?? config('site.phones.1')]));
@endphp
<section style="border-top:1px solid #dcdcdc"><div class="wrap grid" style="grid-template-columns:repeat(auto-fit,minmax(min(100%,380px),1fr));gap:40px;align-items:stretch;padding-top:64px;padding-bottom:64px">
    <div style="display:flex;flex-direction:column;gap:14px">
        <h2 class="h2" style="font-size:clamp(26px,2.8vw,36px)">Visit or contact us</h2>
        <p style="margin:0;font-size:17px;color:#3d3d3d">{{ $s['college_name'] ?? config('site.name') }} for Boys<br>{{ $s['address'] ?? 'Peshawar Road, Rawalpindi' }}</p>
        <p style="margin:0;font-size:17px;color:#3d3d3d">{{ implode(' · ', $phones) }}<br>{{ $s['email'] ?? config('site.email') }}<br>{{ $s['office_hours'] ?? config('site.hours') }}</p>
        <a href="{{ route('site.contact') }}" class="btn btn-green" style="align-self:flex-start">Send a message</a>
    </div>
    <a href="{{ $s['map_url'] ?? config('site.map') }}" class="mapbox" target="_blank" rel="noopener">Open in Google Maps →</a>
</div></section>
