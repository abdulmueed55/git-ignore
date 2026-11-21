@props(['title' => null, 'description' => null, 'menu' => collect(), 's' => []])
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ? $title.' — ' : '' }}{{ $s['college_name'] ?? 'F.G. Liaquat Ali Degree College' }}</title>
    <meta name="description" content="{{ $description ?? ($s['college_tagline'] ?? '') }}">
    <meta property="og:title" content="{{ $title ?? ($s['college_name'] ?? '') }}">
    <meta property="og:description" content="{{ $description ?? '' }}">
    <script @nonce>(() => { let t=null; try { t = localStorage.getItem('theme'); } catch (e) {} document.documentElement.dataset.theme = t || (matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light'); })();</script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>@keyframes portal-marquee { from { transform: translateX(0) } to { transform: translateX(-50%) } }</style>
</head>
<body class="min-h-screen" x-data="{ menu: false }">
    @if (($s['admission_banner_enabled'] ?? false) && ! empty($s['admission_banner_text']))
        <a href="{{ Route::has('apply.start') ? route('apply.start') : url('/pages/admission-policy') }}" class="block bg-brass px-4 py-2 text-center text-[12.5px] font-semibold text-[#1b1405] hover:brightness-95">
            {{ $s['admission_banner_text'] }} <span aria-hidden="true">→</span>
        </a>
    @endif
    <header class="sticky top-0 z-30 border-b border-line bg-paper/90 backdrop-blur-md">
        <div class="mx-auto flex h-16 max-w-6xl items-center gap-3 px-4">
            <a href="{{ route('portal.home') }}" class="flex min-w-0 items-center gap-3">
                <x-crest class="h-10 w-10 shrink-0" />
                <div class="min-w-0 leading-tight">
                    <div class="display truncate text-[15px] font-semibold text-ink">{{ $s['college_name'] ?? 'F.G. Liaquat Ali Degree College' }}</div>
                    <div class="truncate text-[11px] text-muted">Peshawar Road, Rawalpindi</div>
                </div>
            </a>
            <nav class="ml-auto hidden items-center gap-1 text-[13px] lg:flex">
                @foreach ($menu->take(5) as $m)
                    <a href="{{ route('portal.page', $m->slug) }}" class="rounded-md px-2.5 py-1.5 text-ink-2 hover:bg-sunken hover:text-ink">{{ $m->title }}</a>
                @endforeach
            </nav>
            <div class="ml-auto flex items-center gap-2 lg:ml-2">
                <a href="{{ route('login') }}" class="btn btn-ghost btn-sm hidden sm:inline-flex">Staff login</a>
                @if (Route::has('apply.start'))<a href="{{ route('apply.start') }}" class="btn btn-primary btn-sm">Apply</a>@endif
                <button class="btn btn-ghost btn-sm btn-icon !w-8 lg:hidden" @click="menu = !menu" aria-label="Menu"><x-icon name="menu" :size="16" /></button>
            </div>
        </div>
        <div x-show="menu" x-cloak x-transition class="border-t border-line bg-surface px-4 py-3 lg:hidden">
            @foreach ($menu as $m)<a href="{{ route('portal.page', $m->slug) }}" class="block rounded-md px-2 py-2 text-[14px] text-ink-2 hover:bg-sunken">{{ $m->title }}</a>@endforeach
            <a href="{{ route('login') }}" class="block rounded-md px-2 py-2 text-[14px] text-ink-2 hover:bg-sunken">Staff login</a>
        </div>
    </header>

    {{ $slot }}

    <footer class="mt-16 bg-rail text-rail-ink">
        <div class="mx-auto grid max-w-6xl gap-8 px-4 py-12 sm:grid-cols-2 lg:grid-cols-4">
            <div class="sm:col-span-2">
                <div class="flex items-center gap-3"><x-crest class="h-10 w-10" /><div class="display text-[17px]">{{ $s['college_name'] ?? '' }}</div></div>
                <p class="mt-3 max-w-md text-[13px] leading-relaxed text-rail-muted">{{ $s['college_tagline'] ?? '' }}@if (! empty($s['established_year'])) · Est. {{ $s['established_year'] }}@endif</p>
            </div>
            <div class="text-[13px]">
                <div class="eyebrow !text-brass">Contact</div>
                <ul class="mt-3 space-y-2 text-rail-muted">
                    @if (! empty($s['address']))<li class="flex gap-2"><x-icon name="map" :size="15" class="mt-0.5" />{{ $s['address'] }}</li>@endif
                    @if (! empty($s['phone']))<li class="mono flex gap-2"><x-icon name="phone" :size="15" />{{ $s['phone'] }}</li>@endif
                    @if (! empty($s['email']))<li class="flex gap-2"><x-icon name="mail" :size="15" />{{ $s['email'] }}</li>@endif
                    @if (! empty($s['office_hours']))<li class="flex gap-2"><x-icon name="clock" :size="15" />{{ $s['office_hours'] }}</li>@endif
                </ul>
            </div>
            <div class="text-[13px]">
                <div class="eyebrow !text-brass">College</div>
                <ul class="mt-3 space-y-2">
                    @foreach ($menu as $m)<li><a href="{{ route('portal.page', $m->slug) }}" class="text-rail-muted hover:text-rail-ink">{{ $m->title }}</a></li>@endforeach
                </ul>
            </div>
        </div>
        <div class="border-t border-rail-line">
            <div class="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-2 px-4 py-4 text-[11.5px] text-rail-muted">
                <span>© {{ now()->year }} {{ $s['college_name'] ?? '' }}</span>
                <span class="flex gap-3">
                    @foreach (['facebook_url' => 'Facebook', 'youtube_url' => 'YouTube', 'instagram_url' => 'Instagram', 'x_url' => 'X'] as $k => $l)
                        @if (! empty($s[$k]))<a href="{{ $s[$k] }}" target="_blank" rel="noopener" class="hover:text-rail-ink">{{ $l }}</a>@endif
                    @endforeach
                </span>
            </div>
        </div>
    </footer>
</body>
</html>
