@props(['title' => null, 'area' => 'erp'])
@php
    use App\Support\Navigation;
    use App\Support\Dates;
    $user = auth()->user();
    $nav = $area === 'studio' ? Navigation::studio($user) : Navigation::admin($user);
    $palette = Navigation::palette($user, $area);
@endphp
<!DOCTYPE html>
<html lang="en" data-area="{{ $area }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#0a3d26">
    <link rel="manifest" href="{{ asset('staff.webmanifest') }}">
    <link rel="apple-touch-icon" href="{{ asset('images/app-icon-192.png') }}">
    <title>{{ $title ? $title.' · ' : '' }}{{ $area === 'studio' ? 'Website Studio' : 'College ERP' }} — FGLA</title>
    <script @nonce>
        (() => { let t = null; try { t = localStorage.getItem('theme'); } catch (e) {}
          document.documentElement.dataset.theme = t || (matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light'); })();
    </script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('head')
</head>
<body class="min-h-screen" x-data="{ nav: false }">
<div class="flex min-h-screen">
    {{-- ============ Rail ============ --}}
    <div x-show="nav" x-cloak x-transition.opacity class="fixed inset-0 z-30 bg-black/40 lg:hidden" @click="nav=false"></div>
    <aside class="fixed inset-y-0 left-0 z-40 flex w-[256px] -translate-x-full flex-col bg-rail text-rail-ink transition-transform lg:translate-x-0"
           :class="nav ? 'translate-x-0!' : ''">
        <a href="{{ $area === 'studio' ? route('studio.dashboard') : route('dashboard') }}" class="flex items-center gap-3 px-5 pt-5 pb-4">
            <x-crest class="h-12 w-11" />
            <div class="leading-tight">
                <div class="text-[15.5px] font-extrabold tracking-tight">FG Liaquat Ali</div>
                <div class="text-[11px] tracking-wide text-rail-muted">Degree College · Rawalpindi</div>
            </div>
        </a>
        <div class="mx-5 mb-3 flex items-center justify-between rounded-lg border border-rail-line px-3 py-2 text-[11px]">
            <span class="eyebrow !text-rail-muted">{{ $area === 'studio' ? 'Website studio' : 'College ERP' }}</span>
            <span class="mono text-rail-muted">{{ \App\Models\AcademicSession::current()?->name ?? now()->year }}</span>
        </div>
        <nav class="scrollbar-thin flex-1 space-y-5 overflow-y-auto px-4 pb-6 pt-1">
            @foreach ($nav as $group => $items)
                <div>
                    <div class="eyebrow mb-1.5 px-3 !text-[10px] !text-rail-muted/70">{{ $group }}</div>
                    <div class="space-y-0.5">
                        @foreach ($items as $item)
                            <a href="{{ $item['url'] }}" class="rail-link" @if (Navigation::isActive($item)) aria-current="page" @endif>
                                <x-icon :name="$item['icon']" :size="17" />
                                <span class="truncate">{{ $item['label'] }}</span>
                                @isset($badges[$item['route']])
                                    <span class="rail-count">{{ $badges[$item['route']] }}</span>
                                @endisset
                            </a>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </nav>
        <div class="border-t border-rail-line p-4">
            <div class="flex items-center gap-3">
                <div class="grid h-9 w-9 place-items-center rounded-full bg-white text-[12px] font-extrabold text-[#0a3d26]">{{ $user->initials() }}</div>
                <div class="min-w-0 flex-1 leading-tight">
                    <div class="truncate text-[13px] font-semibold">{{ $user->name }}</div>
                    <div class="truncate text-[11px] text-rail-muted">{{ $user->roleLabel() }}</div>
                </div>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button class="grid h-8 w-8 place-items-center rounded-md text-rail-muted hover:bg-white/5 hover:text-rail-ink" title="Sign out"><x-icon name="logout" :size="16" /></button>
                </form>
            </div>
        </div>
    </aside>

    {{-- ============ Main ============ --}}
    <div class="flex min-w-0 flex-1 flex-col lg:pl-[256px]">
        <header class="sticky top-0 z-20 border-b border-line bg-paper/85 backdrop-blur-md">
            <div class="flex h-[60px] items-center gap-3 px-4 sm:px-6 lg:px-8">
                <button class="btn btn-ghost btn-icon lg:hidden" @click="nav=true" aria-label="Open menu"><x-icon name="menu" /></button>
                <div class="hidden min-w-0 items-center gap-2 text-[12.5px] text-muted md:flex">
                    <span>{{ now()->format('l, j F Y') }}</span>
                    <span class="text-line-strong">/</span>
                    <span class="text-brass">{{ Dates::hijri(now()) }}</span>
                </div>
                <div class="ml-auto flex items-center gap-2" x-data="palette(@js($palette))" @keydown.window.ctrl.k.prevent="show()" @keydown.window.meta.k.prevent="show()">
                    <button class="btn btn-ghost h-9 w-9 px-0 sm:w-auto sm:min-w-[240px] sm:justify-start sm:px-3 sm:font-normal sm:text-muted" @click="show()">
                        <x-icon name="search" :size="16" />
                        <span class="hidden sm:inline">Jump to…</span>
                        <kbd class="mono ml-auto hidden rounded border border-line px-1.5 text-[10.5px] sm:inline">Ctrl K</kbd>
                    </button>
                    {{-- Alerts: class reminders, extra classes, other staff notifications --}}
                    <div class="relative" x-data="bell(@js(route('notifications.feed')), @js(route('notifications.read')))" @keydown.escape.window="open = false" @click.outside="open = false">
                        <button type="button" class="btn btn-ghost btn-icon relative" @click="toggle()" :aria-expanded="open" aria-label="Alerts">
                            <x-icon name="bell" :size="16" />
                            <span x-show="unread" x-cloak x-text="unread > 9 ? '9+' : unread" class="mono absolute -right-1 -top-1 grid h-[18px] min-w-[18px] place-items-center rounded-full bg-bad px-1 text-[10px] font-bold text-paper"></span>
                        </button>
                        <div x-show="open" x-cloak x-transition.opacity class="panel absolute right-0 top-11 z-40 w-[min(370px,calc(100vw-24px))] overflow-hidden shadow-2xl">
                            <div class="panel-head">
                                <div class="panel-title">Alerts</div>
                                <button type="button" x-show="unread" @click="readAll()" class="text-[12px] font-semibold text-brand hover:underline">Mark all read</button>
                            </div>
                            <button type="button" x-show="perm === 'default'" @click="askPermission()" class="flex w-full items-center gap-2 border-b border-line bg-brass-soft/50 px-4 py-2.5 text-left text-[12.5px] text-ink">
                                <x-icon name="bell" :size="14" class="text-brass" /> Turn on desktop alerts, so class reminders pop up even in another tab
                            </button>
                            <ul class="max-h-[380px] divide-y divide-line overflow-y-auto">
                                <template x-for="n in items" :key="n.id">
                                    <li>
                                        <a :href="n.url || '#'" @click.prevent="follow(n)" class="flex gap-3 px-4 py-3 hover:bg-sunken/60" :class="n.read ? '' : 'bg-brand-soft/40'">
                                            <span class="mt-1.5 h-2 w-2 shrink-0 rounded-full" :class="n.read ? 'bg-line' : 'bg-brand'"></span>
                                            <span class="min-w-0 flex-1">
                                                <span class="block text-[13px] font-semibold text-ink" x-text="n.title"></span>
                                                <span class="block text-[12px] leading-snug text-muted" x-text="n.body"></span>
                                                <span class="mono mt-0.5 block text-[11px] text-muted" x-text="n.at"></span>
                                            </span>
                                        </a>
                                    </li>
                                </template>
                                <li x-show="!items.length" class="px-4 py-8 text-center text-[12.5px] text-muted">No alerts yet. Class reminders appear here {{ config('timetable.reminder_minutes') }} minutes before each class.</li>
                            </ul>
                        </div>
                        <template x-teleport="body">
                            <div class="fixed bottom-4 right-4 z-[60] grid w-[min(370px,calc(100vw-32px))] gap-2" style="margin-bottom: env(safe-area-inset-bottom, 0px); margin-right: env(safe-area-inset-right, 0px)">
                                <template x-for="t in toasts" :key="t.id">
                                    <div class="panel rise flex items-start gap-3 border-l-4 !border-l-brand p-4 shadow-2xl" role="status">
                                        <x-icon name="bell" :size="18" class="mt-0.5 text-brand" />
                                        <div class="min-w-0 flex-1">
                                            <div class="text-[13.5px] font-bold text-ink" x-text="t.title"></div>
                                            <div class="text-[12.5px] leading-snug text-muted" x-text="t.body"></div>
                                            <a x-show="t.url" :href="t.url" @click="markRead(t.id)" class="mt-1.5 inline-flex items-center gap-1 text-[12.5px] font-semibold text-brand">Open <x-icon name="arrow-right" :size="13" /></a>
                                        </div>
                                        <button type="button" @click="dismiss(t.id)" class="text-muted hover:text-ink" aria-label="Dismiss"><x-icon name="x" :size="14" /></button>
                                    </div>
                                </template>
                            </div>
                        </template>
                    </div>

                    <button class="btn btn-ghost btn-icon" title="Toggle theme"
                            @click="setTheme(document.documentElement.dataset.theme === 'dark' ? 'light' : 'dark')">
                        <x-icon name="moon" :size="16" class="dark:hidden" />
                        <x-icon name="sun" :size="16" class="hidden dark:block" />
                    </button>
                    @if ($area === 'erp' && Route::has('studio.dashboard') && $user->hasRole('admin', 'principal', 'editor'))
                        <a href="{{ route('studio.dashboard') }}" class="btn btn-ghost hidden sm:inline-flex"><x-icon name="globe" :size="16" /> Studio</a>
                    @elseif ($area === 'studio' && $user->role !== 'editor')
                        <a href="{{ route('dashboard') }}" class="btn btn-ghost hidden sm:inline-flex"><x-icon name="grid" :size="16" /> ERP</a>
                    @endif

                    {{-- Palette --}}
                    <template x-teleport="body">
                        <div x-show="open" x-cloak class="fixed inset-0 z-50 grid place-items-start bg-black/35 px-4 pt-[12vh] backdrop-blur-[2px]" @click.self="open=false" @keydown.escape.window="open=false">
                            <div class="panel rise w-full max-w-[560px] overflow-hidden shadow-2xl" @keydown="key($event)">
                                <div class="flex items-center gap-3 border-b border-line px-4">
                                    <x-icon name="search" :size="17" class="text-muted" />
                                    <input x-ref="q" x-model="q" @input="active=0" class="h-12 flex-1 bg-transparent text-[14.5px] outline-none placeholder:text-muted" placeholder="Search modules, pages, actions…">
                                    <kbd class="mono rounded border border-line px-1.5 text-[10.5px] text-muted">Esc</kbd>
                                </div>
                                <ul class="max-h-[340px] overflow-y-auto p-2">
                                    <template x-for="(item, i) in results" :key="item.url">
                                        <li>
                                            <a :href="item.url" @mouseenter="active=i" class="flex items-center justify-between rounded-lg px-3 py-2.5 text-[13.5px]" :class="active===i ? 'bg-sunken text-ink' : 'text-ink-2'">
                                                <span x-text="item.label"></span>
                                                <span class="eyebrow" x-text="item.group"></span>
                                            </a>
                                        </li>
                                    </template>
                                    <li x-show="results.length===0" class="px-3 py-6 text-center text-[13px] text-muted">No module matches “<span x-text="q"></span>”.</li>
                                </ul>
                            </div>
                        </div>
                    </template>
                </div>
            </div>
        </header>

        <main class="flex-1 px-4 py-6 sm:px-6 lg:px-8 lg:py-8">
            <x-ui.flash />
            {{ $slot }}
        </main>

        <footer class="flex flex-wrap items-center justify-between gap-2 border-t border-line px-4 py-4 text-[11.5px] text-muted sm:px-6 lg:px-8">
            <span>FG Liaquat Ali Degree College, Peshawar Road, Rawalpindi</span>
            <span class="mono">v1.0 · {{ app()->environment() }}</span>
        </footer>
    </div>
</div>
@stack('scripts')
</body>
</html>
