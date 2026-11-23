@props(['title' => null])
@php
    $student = auth('student')->user();
    $tabs = [
        ['Home', 'student.home', 'grid'],
        ['Attendance', 'student.attendance', 'check'],
        ['Timetable', 'student.timetable', 'clock'],
        ['Leave', 'student.leave', 'leaf'],
        ['Admission', 'student.application', 'file'],
    ];
@endphp
<!DOCTYPE html>
<html lang="en" data-area="student">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#0a3d26">
    <link rel="manifest" href="{{ asset('student.webmanifest') }}">
    <link rel="apple-touch-icon" href="{{ asset('images/app-icon-192.png') }}">
    <title>{{ $title ? $title.' · ' : '' }}Student portal — FGLA</title>
    <script @nonce>(() => { let t = null; try { t = localStorage.getItem('theme'); } catch (e) {} document.documentElement.dataset.theme = t || (matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light'); })();</script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-paper pb-[calc(72px+env(safe-area-inset-bottom))] lg:pb-0">
    <header class="sticky top-0 z-20 bg-rail text-white" style="padding-top: env(safe-area-inset-top)">
        <div class="mx-auto flex h-[60px] max-w-5xl items-center gap-3 px-4">
            <a href="{{ route('student.home') }}" class="flex min-w-0 items-center gap-2.5">
                <x-crest class="h-10 w-9" />
                <div class="min-w-0 leading-tight">
                    <div class="truncate text-[14.5px] font-extrabold">Student portal</div>
                    <div class="truncate text-[11.5px] text-white/75">FG Liaquat Ali Degree College</div>
                </div>
            </a>
            <nav class="ml-6 hidden items-center gap-1 lg:flex" aria-label="Student portal">
                @foreach ($tabs as [$label, $route, $icon])
                    <a href="{{ route($route) }}" class="rounded-[4px] px-3 py-2 text-[13.5px] font-semibold {{ request()->routeIs($route) ? 'bg-white/15 text-white' : 'text-white/75 hover:text-white' }}">{{ $label }}</a>
                @endforeach
            </nav>
            <div class="ml-auto flex items-center gap-2" x-data="{ menu: false }" @click.outside="menu = false">
                <button type="button" @click="menu = !menu" class="flex items-center gap-2 rounded-[4px] px-2 py-1.5 hover:bg-white/10" :aria-expanded="menu" aria-label="Account">
                    <span class="grid h-8 w-8 place-items-center rounded-full bg-white text-[12px] font-extrabold text-rail">{{ collect(explode(' ', $student->name))->map(fn ($w) => mb_substr($w, 0, 1))->take(2)->implode('') }}</span>
                    <span class="hidden text-left leading-tight sm:block"><span class="block text-[13px] font-semibold">{{ $student->name }}</span><span class="mono block text-[11px] text-white/75">{{ $student->roll_no }}</span></span>
                </button>
                <div x-show="menu" x-cloak x-transition.opacity class="panel absolute right-4 top-[56px] z-30 w-60 overflow-hidden text-ink shadow-2xl">
                    <div class="border-b border-line px-4 py-3"><div class="text-[13.5px] font-semibold">{{ $student->name }}</div><div class="mono text-[12px] text-muted">{{ $student->roll_no }}</div></div>
                    <a href="{{ route('student.password.edit') }}" class="flex items-center gap-2 px-4 py-2.5 text-[13.5px] hover:bg-sunken"><x-icon name="lock" :size="15" /> Change password</a>
                    <button type="button" class="flex w-full items-center gap-2 px-4 py-2.5 text-left text-[13.5px] hover:bg-sunken" @click="setTheme(document.documentElement.dataset.theme === 'dark' ? 'light' : 'dark')"><x-icon name="moon" :size="15" /> Dark / light</button>
                    <form method="POST" action="{{ route('student.logout') }}">@csrf<button class="flex w-full items-center gap-2 px-4 py-2.5 text-left text-[13.5px] text-bad hover:bg-sunken"><x-icon name="logout" :size="15" /> Sign out</button></form>
                </div>
            </div>
        </div>
    </header>

    <main class="mx-auto max-w-5xl px-4 py-5 sm:py-7">
        @if (session('success'))
            <div class="mb-5 rounded-md border border-ok/30 bg-ok-soft px-4 py-3 text-[13.5px] text-ok" role="status">{{ session('success') }}</div>
        @endif
        {{ $slot }}
    </main>

    {{-- Phone: bottom tab bar --}}
    @unless (request()->routeIs('student.password.*'))
        <nav class="fixed inset-x-0 bottom-0 z-20 grid grid-cols-5 border-t border-line bg-surface lg:hidden" style="padding-bottom: env(safe-area-inset-bottom)" aria-label="Student portal">
            @foreach ($tabs as [$label, $route, $icon])
                <a href="{{ route($route) }}" class="flex flex-col items-center gap-0.5 py-2 text-[11px] font-semibold {{ request()->routeIs($route) ? 'text-brand' : 'text-muted' }}" @if (request()->routeIs($route)) aria-current="page" @endif>
                    <x-icon :name="$icon" :size="20" />{{ $label }}
                </a>
            @endforeach
        </nav>
    @endunless
</body>
</html>
