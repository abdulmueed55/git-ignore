@php
    $typeIcon = ['announcement' => 'megaphone', 'news' => 'news', 'event' => 'calendar', 'notice' => 'pin'];
@endphp
<x-portal.layout :menu="$menu" :s="$s" :description="$s['college_tagline'] ?? null">
    {{-- Ticker --}}
    @if ($ticker->isNotEmpty())
        <div class="border-b border-line bg-surface">
            <div class="mx-auto flex max-w-6xl items-center gap-3 px-4 py-2 text-[13px]">
                <span class="badge badge-bad badge-plain shrink-0 !text-[10.5px] uppercase tracking-wider">Latest</span>
                <div class="relative min-w-0 flex-1 overflow-hidden [mask-image:linear-gradient(90deg,transparent,black_3%,black_97%,transparent)]">
                    <div class="flex w-max gap-10 whitespace-nowrap hover:[animation-play-state:paused]" style="animation: portal-marquee {{ max(25, $ticker->count() * 9) }}s linear infinite">
                        @foreach ([1, 2] as $_)
                            @foreach ($ticker as $t)
                                <a href="{{ route('portal.post', $t->slug) }}" class="inline-flex items-center gap-2 text-ink-2 hover:text-brand"><span class="h-1.5 w-1.5 rounded-full bg-brass"></span>{{ $t->title }}</a>
                            @endforeach
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    @endif

    {{-- Hero --}}
    <section class="relative overflow-hidden bg-rail" x-data="{ i: 0, n: {{ max(1, $slides->count()) }} }" x-init="if (n > 1) setInterval(() => i = (i + 1) % n, 7000)">
        @forelse ($slides as $k => $slide)
            <div x-show="i === {{ $k }}" x-transition.opacity.duration.700ms class="relative" @if ($k) x-cloak @endif>
                <img src="{{ $slide->imageUrl() }}" alt="" class="h-[420px] w-full object-cover sm:h-[480px]">
                <div class="absolute inset-0 bg-gradient-to-tr from-black/75 via-black/30 to-transparent"></div>
                <div class="absolute inset-x-0 bottom-0">
                    <div class="mx-auto max-w-6xl px-4 pb-12">
                        <div class="eyebrow !text-[#d4a64a]">{{ $s['college_name'] ?? '' }}</div>
                        <h1 class="display mt-3 max-w-2xl text-[34px] leading-[1.08] text-white sm:text-[48px]">{{ $slide->heading }}</h1>
                        <p class="mt-3 max-w-xl text-[15px] text-white/80">{{ $slide->subheading }}</p>
                        @if ($slide->cta_label)<a href="{{ $slide->cta_url }}" class="btn btn-brass mt-6 h-11 px-5">{{ $slide->cta_label }} <x-icon name="arrow-right" :size="16" /></a>@endif
                    </div>
                </div>
            </div>
        @empty
            <div class="mx-auto max-w-6xl px-4 py-24 text-rail-ink">
                <h1 class="display text-[44px]">{{ $s['college_name'] ?? 'F.G. Liaquat Ali Degree College' }}</h1>
                <p class="mt-3 text-rail-muted">{{ $s['college_tagline'] ?? '' }}</p>
            </div>
        @endforelse
        @if ($slides->count() > 1)
            <div class="absolute bottom-5 right-4 flex gap-1.5 sm:right-8">
                @foreach ($slides as $k => $_)<button @click="i = {{ $k }}" class="h-1.5 rounded-full transition-all" :class="i === {{ $k }} ? 'w-8 bg-[#d4a64a]' : 'w-3 bg-white/50'" aria-label="Slide {{ $k + 1 }}"></button>@endforeach
            </div>
        @endif
    </section>

    {{-- Quick actions --}}
    <div class="mx-auto -mt-8 max-w-6xl px-4">
        <div class="relative grid gap-px overflow-hidden rounded-xl border border-line bg-line shadow-sm sm:grid-cols-4">
            @foreach (array_filter([
                Route::has('apply.start') ? ['Apply online', 'Admissions 2027', route('apply.start'), 'graduation'] : null,
                Route::has('apply.status') ? ['Check status', 'Tracking number', route('apply.status'), 'search'] : null,
                ['Date sheets', 'Downloads', url('/').'#downloads', 'download'],
                ['Staff portal', 'College ERP', route('login'), 'lock'],
            ]) as [$t, $sub, $url, $icon])
                <a href="{{ $url }}" class="group flex items-center gap-3 bg-surface px-5 py-4 hover:bg-sunken">
                    <span class="grid h-10 w-10 place-items-center rounded-lg bg-brand-soft text-brand"><x-icon :name="$icon" :size="18" /></span>
                    <span><span class="block text-[14px] font-semibold text-ink">{{ $t }}</span><span class="block text-[12px] text-muted">{{ $sub }}</span></span>
                    <x-icon name="arrow-right" :size="15" class="ml-auto text-muted transition group-hover:translate-x-0.5 group-hover:text-brand" />
                </a>
            @endforeach
        </div>
    </div>

    <main class="mx-auto max-w-6xl px-4">
        <div class="mt-14 grid gap-10 lg:grid-cols-[1.5fr_1fr]">
            {{-- Announcements & news --}}
            <section>
                <div class="mb-5 flex items-end justify-between border-b border-line pb-3">
                    <h2 class="display text-[26px] text-ink">Announcements & news</h2>
                </div>
                <div class="space-y-5">
                    @forelse ($announcements as $i => $p)
                        <a href="{{ route('portal.post', $p->slug) }}" class="group grid gap-4 {{ $i === 0 && $p->coverUrl() ? 'sm:grid-cols-1' : 'sm:grid-cols-[160px_1fr]' }}">
                            @if ($p->coverUrl())
                                <img src="{{ $p->coverUrl() }}" alt="" class="{{ $i === 0 ? 'aspect-[16/7]' : 'aspect-[4/3] sm:aspect-[4/3]' }} w-full rounded-lg object-cover">
                            @else
                                <div class="hidden aspect-[4/3] place-items-center rounded-lg border border-line bg-sunken text-brass sm:grid"><x-icon :name="$typeIcon[$p->type]" :size="26" /></div>
                            @endif
                            <div>
                                <div class="flex items-center gap-2 text-[11.5px]">
                                    <span class="eyebrow !text-brass">{{ $p->typeLabel() }}</span>
                                    @if ($p->is_pinned)<span class="badge badge-brass badge-plain !h-5 !text-[10px]">Important</span>@endif
                                    <span class="text-muted">{{ $p->published_at->format('j M Y') }}</span>
                                </div>
                                <h3 class="display mt-1 text-[19px] leading-snug text-ink group-hover:text-brand">{{ $p->title }}</h3>
                                <p class="mt-1 line-clamp-2 text-[13.5px] text-muted">{{ $p->excerpt }}</p>
                            </div>
                        </a>
                    @empty
                        <p class="text-[14px] text-muted">There are no announcements at the moment.</p>
                    @endforelse
                </div>
            </section>

            {{-- Notice board --}}
            <aside>
                <div class="rounded-xl border border-line bg-surface">
                    <div class="flex items-center justify-between border-b border-line px-5 py-4">
                        <h2 class="display text-[20px] text-ink">Notice board</h2><x-icon name="pin" class="text-brass" />
                    </div>
                    <ul class="divide-y divide-line">
                        @forelse ($notices as $n)
                            <li>
                                <a href="{{ route('portal.post', $n->slug) }}" class="flex gap-4 px-5 py-3.5 hover:bg-sunken/60">
                                    <div class="w-11 shrink-0 text-center"><div class="numeral text-[22px] text-ink">{{ $n->published_at->format('d') }}</div><div class="eyebrow !text-[9px]">{{ $n->published_at->format('M') }}</div></div>
                                    <div class="min-w-0">
                                        <div class="text-[13.5px] font-semibold leading-snug text-ink">{{ $n->title }}</div>
                                        @if ($n->attachment_path)<div class="mt-1 inline-flex items-center gap-1 text-[11.5px] text-bad"><x-icon name="file" :size="12" /> PDF attached</div>@endif
                                    </div>
                                </a>
                            </li>
                        @empty
                            <li class="px-5 py-6 text-[13px] text-muted">No notices at the moment.</li>
                        @endforelse
                    </ul>
                </div>
            </aside>
        </div>

        {{-- Events --}}
        @if ($events->isNotEmpty())
            <section class="mt-16">
                <div class="mb-5 border-b border-line pb-3"><h2 class="display text-[26px] text-ink">Upcoming events</h2></div>
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    @foreach ($events as $e)
                        <a href="{{ route('portal.post', $e->slug) }}" class="group overflow-hidden rounded-xl border border-line bg-surface hover:border-line-strong">
                            <div class="flex items-center gap-3 border-b border-line bg-brand-soft/50 px-4 py-3">
                                <div class="text-center"><div class="numeral text-[28px] text-brand">{{ $e->event_starts_at->format('d') }}</div><div class="eyebrow !text-[9px]">{{ $e->event_starts_at->format('M Y') }}</div></div>
                                <div class="text-[12px] text-ink-2">{{ $e->event_starts_at->format('l') }}<br>{{ $e->event_starts_at->format('g:i A') }}</div>
                            </div>
                            <div class="p-4">
                                <h3 class="text-[14.5px] font-semibold leading-snug text-ink group-hover:text-brand">{{ $e->title }}</h3>
                                <div class="mt-2 flex items-center gap-1.5 text-[12px] text-muted"><x-icon name="map" :size="13" />{{ $e->venue }}</div>
                            </div>
                        </a>
                    @endforeach
                </div>
            </section>
        @endif

        <div class="mt-16 grid gap-10 lg:grid-cols-[1fr_1.2fr]">
            {{-- Principal --}}
            @if (! empty($s['principal_message']))
                <section class="rounded-xl border border-line bg-surface p-7">
                    <div class="eyebrow !text-brass">From the Principal</div>
                    <blockquote class="display mt-4 text-[19px] leading-relaxed text-ink">“{{ \Illuminate\Support\Str::of($s['principal_message'])->before("\n")->limit(320) }}”</blockquote>
                    <div class="mt-5 flex items-center gap-3">
                        <x-ui.avatar :name="$s['principal_name'] ?? 'P'" :size="40" />
                        <div><div class="text-[13.5px] font-semibold text-ink">{{ $s['principal_name'] ?? '' }}</div><div class="text-[12px] text-muted">{{ $s['principal_title'] ?? '' }}</div></div>
                    </div>
                    <a href="{{ route('portal.page', 'principals-message') }}" class="mt-5 inline-flex items-center gap-1 text-[13px] font-semibold text-brand hover:underline">Read the full message <x-icon name="arrow-right" :size="14" /></a>
                </section>
            @endif

            {{-- Downloads --}}
            <section id="downloads">
                <div class="mb-4 border-b border-line pb-3"><h2 class="display text-[26px] text-ink">Downloads</h2></div>
                <ul class="divide-y divide-line rounded-xl border border-line bg-surface">
                    @forelse ($downloads as $d)
                        <li>
                            <a href="{{ route('portal.download', $d) }}" class="flex items-center gap-3 px-4 py-3 hover:bg-sunken/60">
                                <span class="grid h-9 w-9 shrink-0 place-items-center rounded-md bg-bad-soft text-[10px] font-bold uppercase text-bad">{{ pathinfo($d->file_name, PATHINFO_EXTENSION) }}</span>
                                <span class="min-w-0 flex-1"><span class="block truncate text-[13.5px] font-medium text-ink">{{ $d->title }}</span><span class="block text-[11.5px] text-muted">{{ $d->categoryLabel() }} · {{ $d->sizeLabel() }}</span></span>
                                <x-icon name="download" :size="16" class="text-muted" />
                            </a>
                        </li>
                    @empty
                        <li class="px-4 py-6 text-[13px] text-muted">No downloads available.</li>
                    @endforelse
                </ul>
            </section>
        </div>

        {{-- Gallery --}}
        @if ($albums->isNotEmpty())
            <section class="mt-16">
                <div class="mb-5 border-b border-line pb-3"><h2 class="display text-[26px] text-ink">Campus life</h2></div>
                <div class="grid gap-4 sm:grid-cols-3">
                    @foreach ($albums as $a)
                        <figure class="group relative overflow-hidden rounded-xl">
                            @if ($a->images->first())<img src="{{ $a->images->first()->url() }}" alt="" class="aspect-[4/3] w-full object-cover transition duration-500 group-hover:scale-[1.03]">@endif
                            <figcaption class="absolute inset-x-0 bottom-0 bg-gradient-to-t from-black/75 to-transparent p-4 text-white">
                                <div class="text-[14px] font-semibold">{{ $a->title }}</div>
                                <div class="text-[11.5px] text-white/70">{{ $a->taken_on?->format('F Y') }} · {{ $a->images->count() }} photos</div>
                            </figcaption>
                        </figure>
                    @endforeach
                </div>
            </section>
        @endif
    </main>
</x-portal.layout>
