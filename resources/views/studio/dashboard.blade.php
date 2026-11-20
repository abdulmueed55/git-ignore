{{-- tw-safelist: bg-bad-soft text-bad bg-warn-soft text-warn bg-info-soft text-info --}}
<x-layouts.app title="Studio overview" area="studio">
    <x-ui.page-header eyebrow="Website studio" title="What visitors see today"
        description="Everything on the public website — announcements, notices, events, pages and media — is published from here.">
        <x-slot:actions>
            <a href="{{ route('portal.home') }}" target="_blank" class="btn btn-ghost"><x-icon name="arrow-up-right" :size="16" /> View website</a>
            <a href="{{ route('studio.posts.create', ['type' => 'notice']) }}" class="btn btn-ghost"><x-icon name="pin" :size="16" /> New notice</a>
            <a href="{{ route('studio.posts.create', ['type' => 'announcement']) }}" class="btn btn-primary"><x-icon name="plus" :size="16" /> New announcement</a>
        </x-slot:actions>
    </x-ui.page-header>

    {{-- ===== Totals ===== --}}
    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-ui.stat label="Live on the site" :value="$totals['live']" icon="globe" :href="route('studio.posts.index', ['state' => 'live'])">
            <div class="mt-3 flex flex-wrap gap-1.5">
                @foreach ($liveByType as $t => $c)
                    <span class="badge badge-plain mono !text-[10.5px]">{{ $c }} {{ \Illuminate\Support\Str::plural(\App\Models\Post::TYPES[$t], $c) }}</span>
                @endforeach
            </div>
        </x-ui.stat>
        <x-ui.stat label="Scheduled" :value="$totals['scheduled']" icon="clock" :href="route('studio.posts.index', ['state' => 'scheduled'])"
            :hint="$scheduled->first() ? 'next goes live '.$scheduled->first()->published_at->diffForHumans() : 'nothing queued'" />
        <x-ui.stat label="Drafts" :value="$totals['drafts']" icon="edit" :href="route('studio.posts.index', ['state' => 'draft'])" hint="waiting to be finished" />
        <x-ui.stat label="Reads · 30 days" :value="number_format($totals['views30'])" icon="eye" hint="across posts published this month" />
    </div>

    <div class="mt-6 grid grid-cols-1 gap-6 xl:grid-cols-[1.6fr_1fr] [&>*]:min-w-0">
        {{-- ===== On the site now ===== --}}
        <section class="panel overflow-hidden">
            <div class="panel-head">
                <div>
                    <div class="panel-title">On the site right now</div>
                    <div class="text-[12px] text-muted">Pinned items appear first on the home page.</div>
                </div>
                <a href="{{ route('studio.posts.index', ['state' => 'live']) }}" class="btn btn-ghost btn-sm">All live <x-icon name="arrow-right" :size="14" /></a>
            </div>

            {{-- Ticker preview --}}
            <div class="flex items-center gap-3 border-b border-line bg-rail px-4 py-2.5 text-[12.5px] text-rail-ink">
                <span class="badge badge-brass badge-plain shrink-0 !h-5 !text-[10px] uppercase tracking-wider">Ticker</span>
                @if (! $tickerEnabled)
                    <span class="text-rail-muted">The ticker is switched off in <a class="underline" href="{{ route('studio.settings') }}#banners">settings</a>.</span>
                @elseif ($ticker->isEmpty())
                    <span class="text-rail-muted">Nothing in the ticker — tick “Show in ticker” on an announcement.</span>
                @else
                    <div class="relative min-w-0 flex-1 overflow-hidden [mask-image:linear-gradient(90deg,transparent,black_4%,black_96%,transparent)]">
                        <div class="flex w-max gap-8 whitespace-nowrap" style="animation: studio-marquee {{ max(18, $ticker->count() * 8) }}s linear infinite">
                            @foreach ([1, 2] as $_)
                                @foreach ($ticker as $t)
                                    <span class="inline-flex items-center gap-2"><span class="h-1 w-1 rounded-full bg-brass"></span>{{ $t->title }}</span>
                                @endforeach
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>

            <ul class="divide-y divide-line">
                @forelse ($live->take(7) as $post)
                    <li class="flex items-center gap-4 px-5 py-3">
                        <div class="w-[68px] shrink-0 text-center">
                            <div class="numeral text-[22px] text-ink">{{ $post->published_at->format('d') }}</div>
                            <div class="eyebrow !text-[9.5px]">{{ $post->published_at->format('M') }}</div>
                        </div>
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center gap-2">
                                @if ($post->is_pinned)<x-icon name="pin" :size="13" class="text-brass" />@endif
                                <a href="{{ route('studio.posts.edit', [$post, 'type' => $post->type]) }}" class="truncate text-[13.5px] font-semibold text-ink hover:text-brand">{{ $post->title }}</a>
                            </div>
                            <div class="mt-0.5 flex flex-wrap items-center gap-x-3 text-[12px] text-muted">
                                <span>{{ $post->typeLabel() }} · {{ $post->category }}</span>
                                @if ($post->expires_at)<span>hides {{ $post->expires_at->diffForHumans() }}</span>@endif
                                @if ($post->type === 'event' && $post->event_starts_at)<span class="text-brand">{{ $post->event_starts_at->format('D j M, g:i A') }}</span>@endif
                            </div>
                        </div>
                        <div class="hidden text-right sm:block">
                            <div class="mono text-[12.5px] text-ink">{{ number_format($post->views) }}</div>
                            <div class="text-[11px] text-muted">reads</div>
                        </div>
                    </li>
                @empty
                    <li><x-ui.empty icon="globe" title="The website has nothing live" description="Publish an announcement so visitors see something current.">
                        <a href="{{ route('studio.posts.create') }}" class="btn btn-primary btn-sm">Write an announcement</a>
                    </x-ui.empty></li>
                @endforelse
            </ul>
        </section>

        {{-- ===== Quick compose ===== --}}
        <section class="panel">
            <div class="panel-head">
                <div>
                    <div class="panel-title">Quick announcement</div>
                    <div class="text-[12px] text-muted">Goes live immediately. Use the full editor for covers, PDFs and scheduling.</div>
                </div>
                <x-icon name="megaphone" class="text-brass" />
            </div>
            <form method="POST" action="{{ route('studio.posts.store') }}" class="space-y-4 p-5" x-data="{ ex: @js(old('excerpt', '')) }">
                @csrf
                <input type="hidden" name="type" value="announcement">
                <input type="hidden" name="publish_mode" value="now">
                <x-ui.field name="title" label="Headline" required placeholder="e.g. College closed on Monday due to rain" />
                <div>
                    <x-ui.field name="excerpt" label="Summary" type="textarea" required rows="2" maxlength="400" x-model="ex"
                        placeholder="One or two sentences shown in lists and the ticker." class="[&_textarea]:!min-h-[64px]" />
                    <div class="mt-1 text-right text-[11px] text-muted"><span class="mono" x-text="ex.length"></span>/400</div>
                </div>
                <x-ui.field name="body" label="Full text" type="textarea" required rows="3" placeholder="The complete announcement. Blank lines start a new paragraph." />
                <div class="grid grid-cols-2 gap-3">
                    <x-ui.field name="category" label="Category" type="select" required :options="array_combine($categories, $categories)" value="Administration" />
                    <x-ui.field name="expires_at" label="Hide after" type="date" hint="Optional" />
                </div>
                <label class="flex items-center gap-2 text-[13px] text-ink-2"><input type="checkbox" name="show_in_ticker" value="1" class="checkbox" @checked(old('show_in_ticker'))> Also show in the news ticker</label>
                <button class="btn btn-primary w-full"><x-icon name="megaphone" :size="16" /> Publish now</button>
            </form>
        </section>
    </div>

    {{-- ===== Publishing calendar ===== --}}
    <section class="panel mt-6">
        <div class="panel-head">
            <div>
                <div class="panel-title">Publishing calendar</div>
                <div class="text-[12px] text-muted">Two weeks back, three ahead. Dots are posts going live; brass bars are events.</div>
            </div>
            <div class="hidden items-center gap-4 text-[11.5px] text-muted sm:flex">
                <span class="inline-flex items-center gap-1.5"><span class="h-2 w-2 rounded-full bg-brand"></span>Announcement / news</span>
                <span class="inline-flex items-center gap-1.5"><span class="h-2 w-2 rounded-full bg-info"></span>Notice</span>
                <span class="inline-flex items-center gap-1.5"><span class="h-1.5 w-4 rounded-sm bg-brass"></span>Event day</span>
            </div>
        </div>
        <div class="overflow-x-auto p-4">
            <div class="grid min-w-[700px] grid-cols-7 gap-1.5">
                @foreach (['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'] as $d)
                    <div class="eyebrow px-1 pb-1 !text-[9.5px]">{{ $d }}</div>
                @endforeach
                @foreach ($calendar as $day)
                    @php $today = $day['date']->isToday(); $past = $day['date']->lt(today()); @endphp
                    <div class="group relative min-h-[78px] rounded-lg border p-2 {{ $today ? 'border-brass bg-brass-soft/50' : 'border-line '.($past ? 'bg-sunken/40' : 'bg-surface') }}">
                        <div class="flex items-center justify-between">
                            <span class="mono text-[11px] {{ $today ? 'font-bold text-brass' : ($past ? 'text-muted' : 'text-ink-2') }}">{{ $day['date']->format($day['date']->day === 1 ? 'j M' : 'j') }}</span>
                            @if ($today)<span class="eyebrow !text-[8.5px] !text-brass">Today</span>@endif
                        </div>
                        @foreach ($day['events'] as $e)
                            <div class="mt-1 truncate rounded-sm bg-brass px-1 text-[10px] font-semibold leading-4 text-[#1b1405]" title="{{ $e->title }}">{{ $e->title }}</div>
                        @endforeach
                        <div class="mt-1.5 flex flex-wrap gap-1">
                            @foreach ($day['posts'] as $p)
                                <a href="{{ route('studio.posts.edit', [$p->id, 'type' => $p->type]) }}" title="{{ $p->typeLabel() }}: {{ $p->title }}"
                                   class="h-2 w-2 rounded-full {{ $p->type === 'notice' ? 'bg-info' : ($p->type === 'event' ? 'bg-brass' : 'bg-brand') }} {{ $p->published_at->isFuture() ? 'ring-2 ring-offset-1 ring-offset-surface ring-current opacity-60' : '' }}"></a>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- ===== Queues ===== --}}
    <div class="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-3 [&>*]:min-w-0">
        <section class="panel">
            <div class="panel-head"><div class="panel-title">Scheduled queue</div><span class="badge badge-info">{{ $totals['scheduled'] }}</span></div>
            <ul class="divide-y divide-line">
                @forelse ($scheduled as $p)
                    <li class="px-5 py-3">
                        <a href="{{ route('studio.posts.edit', [$p, 'type' => $p->type]) }}" class="block truncate text-[13px] font-semibold text-ink hover:text-brand">{{ $p->title }}</a>
                        <div class="mt-0.5 flex items-center gap-2 text-[11.5px] text-muted">
                            <x-icon name="clock" :size="12" /> <span class="mono">{{ $p->published_at->format('D j M · g:i A') }}</span>
                            <span>· {{ $p->published_at->diffForHumans() }}</span>
                        </div>
                    </li>
                @empty
                    <li><x-ui.empty icon="clock" title="Nothing scheduled" description="Schedule notices in advance — they go live on their own.">
                        <a href="{{ route('studio.posts.create', ['type' => 'notice']) }}" class="btn btn-ghost btn-sm">Schedule a notice</a>
                    </x-ui.empty></li>
                @endforelse
            </ul>
        </section>

        <section class="panel">
            <div class="panel-head"><div class="panel-title">Hiding within 7 days</div><span class="badge badge-warn">{{ $expiring->count() }}</span></div>
            <ul class="divide-y divide-line">
                @forelse ($expiring as $p)
                    <li class="flex items-center justify-between gap-3 px-5 py-3">
                        <div class="min-w-0">
                            <a href="{{ route('studio.posts.edit', [$p, 'type' => $p->type]) }}" class="block truncate text-[13px] font-semibold text-ink hover:text-brand">{{ $p->title }}</a>
                            <div class="text-[11.5px] text-muted">{{ $p->typeLabel() }} · expires {{ $p->expires_at->format('j M') }}</div>
                        </div>
                        <span class="mono shrink-0 text-[12px] text-warn">{{ (int) ceil(now()->floatDiffInDays($p->expires_at)) }}d</span>
                    </li>
                @empty
                    <li><x-ui.empty icon="calendar" title="Nothing about to expire" description="Items you give an expiry date will be listed here a week before they disappear." /></li>
                @endforelse
            </ul>
        </section>

        <section class="panel">
            <div class="panel-head"><div class="panel-title">Unfinished drafts</div><span class="badge badge-warn">{{ $totals['drafts'] }}</span></div>
            <ul class="divide-y divide-line">
                @forelse ($drafts as $p)
                    <li class="flex items-center justify-between gap-3 px-5 py-3">
                        <div class="min-w-0">
                            <a href="{{ route('studio.posts.edit', [$p, 'type' => $p->type]) }}" class="block truncate text-[13px] font-semibold text-ink hover:text-brand">{{ $p->title }}</a>
                            <div class="text-[11.5px] text-muted">{{ $p->typeLabel() }} · edited {{ $p->updated_at->diffForHumans() }}</div>
                        </div>
                        <a href="{{ route('studio.posts.edit', [$p, 'type' => $p->type]) }}" class="btn btn-ghost btn-sm shrink-0">Finish</a>
                    </li>
                @empty
                    <li><x-ui.empty icon="edit" title="No drafts" description="Everything you started has been published." /></li>
                @endforelse
            </ul>
        </section>
    </div>

    {{-- ===== Health, popular, inbox ===== --}}
    <div class="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-3 [&>*]:min-w-0">
        <section class="panel lg:col-span-1">
            <div class="panel-head">
                <div class="panel-title">Content health</div>
                @if (count($health))<span class="badge badge-warn">{{ count($health) }} to fix</span>@else<span class="badge badge-ok">All good</span>@endif
            </div>
            <ul class="divide-y divide-line">
                @forelse ($health as [$tone, $title, $why, $url])
                    <li>
                        <a href="{{ $url }}" class="flex gap-3 px-5 py-3 hover:bg-sunken/50">
                            <span class="mt-0.5 grid h-6 w-6 shrink-0 place-items-center rounded-md bg-{{ $tone }}-soft text-{{ $tone }}"><x-icon :name="$tone === 'bad' ? 'alert' : ($tone === 'warn' ? 'alert' : 'info')" :size="13" /></span>
                            <span class="min-w-0">
                                <span class="block text-[13px] font-semibold text-ink">{{ $title }}</span>
                                <span class="block text-[12px] text-muted">{{ $why }}</span>
                            </span>
                            <x-icon name="chevron-right" :size="14" class="ml-auto mt-1 text-muted" />
                        </a>
                    </li>
                @empty
                    <li><x-ui.empty icon="shield" title="No problems found" description="Covers, expiry dates, ticker and the principal's message all look current." /></li>
                @endforelse
            </ul>
        </section>

        <section class="panel">
            <div class="panel-head"><div class="panel-title">Most read</div><span class="eyebrow">live items</span></div>
            @php $maxViews = max(1, (int) $popular->max('views')); @endphp
            <ol class="space-y-3.5 p-5">
                @forelse ($popular as $i => $p)
                    <li>
                        <div class="flex items-baseline justify-between gap-3 text-[13px]">
                            <a href="{{ route('studio.posts.edit', [$p, 'type' => $p->type]) }}" class="truncate font-medium text-ink hover:text-brand"><span class="mono mr-2 text-muted">{{ $i + 1 }}</span>{{ $p->title }}</a>
                            <span class="mono shrink-0 text-[12px] text-ink-2">{{ number_format($p->views) }}</span>
                        </div>
                        <div class="mt-1.5 h-1.5 overflow-hidden rounded-full bg-sunken"><div class="h-full rounded-full bg-brand" style="width: {{ round($p->views / $maxViews * 100) }}%"></div></div>
                    </li>
                @empty
                    <li class="text-[13px] text-muted">Reads appear once visitors open live posts.</li>
                @endforelse
            </ol>
        </section>

        <section class="panel">
            <div class="panel-head">
                <div class="panel-title">Contact inbox</div>
                <a href="{{ route('studio.messages.index', ['filter' => 'unread']) }}" class="badge {{ $unreadCount ? 'badge-brass' : 'badge-ok' }}">{{ $unreadCount }} unread</a>
            </div>
            <ul class="divide-y divide-line">
                @forelse ($unread as $m)
                    <li>
                        <a href="{{ route('studio.messages.show', $m) }}" class="flex gap-3 px-5 py-3 hover:bg-sunken/50">
                            <x-ui.avatar :name="$m->name" :size="30" />
                            <span class="min-w-0 flex-1">
                                <span class="flex justify-between gap-2"><span class="truncate text-[13px] font-semibold text-ink">{{ $m->name }}</span><span class="shrink-0 text-[11px] text-muted">{{ $m->created_at->diffForHumans(short: true) }}</span></span>
                                <span class="block truncate text-[12px] text-ink-2">{{ $m->subject }}</span>
                            </span>
                        </a>
                    </li>
                @empty
                    <li><x-ui.empty icon="mail" title="Inbox zero" description="New messages from the website contact form arrive here." /></li>
                @endforelse
            </ul>
        </section>
    </div>

    {{-- ===== Library ===== --}}
    <div class="mt-6 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        @foreach ([
            ['Pages', $totals['pages'], 'file', 'studio.pages.index', 'menu & static content'],
            ['Gallery albums', $totals['albums'], 'images', 'studio.gallery.index', 'published albums'],
            ['Downloads', $totals['downloads'], 'download', 'studio.downloads.index', 'forms, date sheets, syllabi'],
            ['Faculty profiles', $totals['faculty'], 'id', 'studio.faculty.index', 'in the public directory'],
        ] as [$label, $n, $icon, $route, $hint])
            <a href="{{ route($route) }}" class="panel group flex items-center gap-4 p-4 hover:border-line-strong">
                <span class="grid h-10 w-10 place-items-center rounded-lg bg-sunken text-muted group-hover:text-brand"><x-icon :name="$icon" /></span>
                <span class="min-w-0 flex-1">
                    <span class="block text-[13px] font-semibold text-ink">{{ $label }}</span>
                    <span class="block text-[11.5px] text-muted">{{ $hint }}</span>
                </span>
                <span class="numeral text-[26px] text-ink">{{ $n }}</span>
            </a>
        @endforeach
    </div>

    @push('head')
        <style>@keyframes studio-marquee { from { transform: translateX(0); } to { transform: translateX(-50%); } }</style>
    @endpush
</x-layouts.app>
