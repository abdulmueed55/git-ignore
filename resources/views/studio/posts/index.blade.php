@php
    use App\Models\Post;
    $label = $type ? \Illuminate\Support\Str::plural(Post::TYPES[$type]) : 'All posts';
    $blurb = [
        'announcement' => 'Short, timely messages for students and parents — shown on the home page and in the ticker.',
        'news' => 'Stories about college life: results, achievements, visits and campus updates.',
        'event' => 'Dated happenings with a venue. Upcoming events are listed on the home page in date order.',
        'notice' => 'Official notices — date sheets, holidays, timings. Attach the signed PDF.',
    ][$type] ?? 'Every announcement, news story, event and notice on the website.';
    $states = ['all' => 'All', 'live' => 'Live', 'scheduled' => 'Scheduled', 'draft' => 'Drafts', 'expired' => 'Expired', 'archived' => 'Archived'];
@endphp
<x-layouts.app :title="$label" area="studio">
    <x-ui.page-header eyebrow="Publish" :title="$label" :description="$blurb">
        <x-slot:actions>
            <a href="{{ route('studio.posts.create', ['type' => $type ?? 'announcement']) }}" class="btn btn-primary"><x-icon name="plus" :size="16" /> New {{ strtolower(Post::TYPES[$type ?? 'announcement']) }}</a>
        </x-slot:actions>
    </x-ui.page-header>

    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <div class="tabs" role="tablist">
            @foreach ($states as $k => $l)
                <a role="tab" href="{{ route('studio.posts.index', array_filter(['type' => $type, 'state' => $k === 'all' ? null : $k, 'q' => $q ?: null])) }}"
                   aria-selected="{{ ($state ?? 'all') === $k || (! $state && $k === 'all') ? 'true' : 'false' }}" class="tab">
                    {{ $l }} <span class="mono text-[10.5px] opacity-70">{{ $counts[$k] }}</span>
                </a>
            @endforeach
        </div>
        <form method="GET" class="flex items-center gap-2">
            @if ($type)<input type="hidden" name="type" value="{{ $type }}">@endif
            @if ($state)<input type="hidden" name="state" value="{{ $state }}">@endif
            <select name="category" class="input !h-9 !w-auto !text-[12.5px]" onchange="this.form.submit()">
                <option value="">All categories</option>
                @foreach ($categories as $c)<option @selected(request('category') === $c)>{{ $c }}</option>@endforeach
            </select>
            <div class="relative">
                <x-icon name="search" :size="15" class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-muted" />
                <input name="q" value="{{ $q }}" class="input !h-9 !w-56 !pl-9 !text-[12.5px]" placeholder="Search title or summary">
            </div>
        </form>
    </div>

    <form method="POST" action="{{ route('studio.posts.bulk') }}" x-data="{ ids: [], all: false }" class="panel overflow-hidden">
        @csrf
        <div x-show="ids.length" x-cloak class="flex flex-wrap items-center gap-2 border-b border-line bg-brass-soft/40 px-4 py-2.5 text-[12.5px]">
            <span class="font-semibold text-ink"><span x-text="ids.length"></span> selected</span>
            <span class="text-line-strong">|</span>
            <button name="action" value="publish" class="btn btn-ghost btn-sm"><x-icon name="globe" :size="14" /> Publish</button>
            <button name="action" value="draft" class="btn btn-ghost btn-sm">Move to drafts</button>
            <button name="action" value="archive" class="btn btn-ghost btn-sm">Archive</button>
            <button name="action" value="delete" class="btn btn-danger btn-sm" onclick="return confirm('Delete the selected items permanently?')"><x-icon name="trash" :size="14" /> Delete</button>
        </div>
        @error('ids')<div class="field-error px-4 pt-3">{{ $message }}</div>@enderror

        @if ($posts->isEmpty())
            <x-ui.empty icon="{{ ['announcement' => 'megaphone', 'news' => 'news', 'event' => 'calendar', 'notice' => 'pin'][$type] ?? 'file' }}"
                title="{{ $q ? 'Nothing matches “'.$q.'”' : 'No '.strtolower($label).' here yet' }}"
                description="{{ $q ? 'Try a different word or clear the filters.' : 'Create the first one — it can go live now or be scheduled.' }}">
                @if ($q || $state || request('category'))
                    <a href="{{ route('studio.posts.index', array_filter(['type' => $type])) }}" class="btn btn-ghost btn-sm">Clear filters</a>
                @endif
                <a href="{{ route('studio.posts.create', ['type' => $type ?? 'announcement']) }}" class="btn btn-primary btn-sm"><x-icon name="plus" :size="14" /> Create</a>
            </x-ui.empty>
        @else
            <div class="overflow-x-auto">
                <table class="table">
                    <thead>
                        <tr>
                            <th class="w-10"><input type="checkbox" class="checkbox" x-model="all" @change="ids = all ? @js($posts->pluck('id')->map(fn ($i) => (string) $i)) : []"></th>
                            <th>Title</th>
                            @unless ($type)<th>Type</th>@endunless
                            <th>Status</th>
                            <th>{{ $type === 'event' ? 'Event date' : 'Goes live' }}</th>
                            <th class="num">Reads</th>
                            <th class="w-10"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($posts as $post)
                            @php $live = $post->liveState(); @endphp
                            <tr>
                                <td><input type="checkbox" name="ids[]" value="{{ $post->id }}" class="checkbox" x-model="ids"></td>
                                <td class="max-w-[460px]">
                                    <div class="flex items-center gap-3">
                                        @if ($post->coverUrl())
                                            <img src="{{ $post->coverUrl() }}" alt="" class="h-10 w-14 shrink-0 rounded-md object-cover">
                                        @else
                                            <span class="grid h-10 w-14 shrink-0 place-items-center rounded-md border border-dashed border-line-strong text-muted"><x-icon :name="['announcement' => 'megaphone', 'news' => 'news', 'event' => 'calendar', 'notice' => 'pin'][$post->type]" :size="15" /></span>
                                        @endif
                                        <div class="min-w-0">
                                            <a href="{{ route('studio.posts.edit', [$post, 'type' => $post->type]) }}" class="flex items-center gap-1.5 font-semibold text-ink hover:text-brand">
                                                @if ($post->is_pinned)<x-icon name="pin" :size="13" class="text-brass" />@endif
                                                <span class="truncate">{{ $post->title }}</span>
                                            </a>
                                            <div class="mt-0.5 flex flex-wrap gap-x-2 text-[11.5px] text-muted">
                                                <span>{{ $post->category }}</span>
                                                @if ($post->attachment_path)<span class="inline-flex items-center gap-1"><x-icon name="file" :size="11" /> PDF</span>@endif
                                                @if ($post->show_in_ticker)<span class="text-brass">ticker</span>@endif
                                                <span>by {{ $post->author?->name ?? 'Office' }}</span>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                @unless ($type)<td><span class="badge badge-plain">{{ $post->typeLabel() }}</span></td>@endunless
                                <td><x-ui.status :value="$live" /></td>
                                <td class="whitespace-nowrap text-[12.5px]">
                                    @if ($type === 'event' && $post->event_starts_at)
                                        <div class="text-ink">{{ $post->event_starts_at->format('D j M Y') }}</div>
                                        <div class="text-[11.5px] text-muted">{{ $post->event_starts_at->format('g:i A') }} · {{ $post->venue }}</div>
                                    @elseif ($post->published_at)
                                        <div class="text-ink">{{ $post->published_at->format('j M Y, g:i A') }}</div>
                                        <div class="text-[11.5px] text-muted">{{ $post->expires_at ? 'hides '.$post->expires_at->format('j M') : $post->published_at->diffForHumans() }}</div>
                                    @else
                                        <span class="text-muted">Not set</span>
                                    @endif
                                </td>
                                <td class="num mono">{{ number_format($post->views) }}</td>
                                <td>
                                    <div x-data="{ open: false }" class="relative">
                                        <button type="button" class="btn btn-ghost btn-sm btn-icon !h-8 !w-8" @click="open = !open" @click.outside="open=false" aria-label="Actions"><x-icon name="more" :size="16" /></button>
                                        <div x-show="open" x-cloak x-transition.opacity class="absolute right-0 z-20 mt-1 w-48 overflow-hidden rounded-lg border border-line bg-surface py-1 text-[13px] shadow-lg">
                                            <a href="{{ route('studio.posts.edit', [$post, 'type' => $post->type]) }}" class="flex items-center gap-2 px-3 py-2 hover:bg-sunken"><x-icon name="edit" :size="14" /> Edit</a>
                                            @if ($live === 'live')
                                                <a href="{{ route('portal.post', $post->slug) }}" target="_blank" class="flex items-center gap-2 px-3 py-2 hover:bg-sunken"><x-icon name="arrow-up-right" :size="14" /> View on site</a>
                                            @endif
                                            <button form="pin-{{ $post->id }}" class="flex w-full items-center gap-2 px-3 py-2 text-left hover:bg-sunken"><x-icon name="pin" :size="14" /> {{ $post->is_pinned ? 'Unpin' : 'Pin to top' }}</button>
                                            <button form="dup-{{ $post->id }}" class="flex w-full items-center gap-2 px-3 py-2 text-left hover:bg-sunken"><x-icon name="files" :size="14" /> Duplicate</button>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="border-t border-line px-4 py-3">{{ $posts->links() }}</div>
        @endif
    </form>

    @foreach ($posts as $post)
        <form id="pin-{{ $post->id }}" method="POST" action="{{ route('studio.posts.pin', $post) }}" class="hidden">@csrf @method('PATCH')</form>
        <form id="dup-{{ $post->id }}" method="POST" action="{{ route('studio.posts.duplicate', $post) }}" class="hidden">@csrf</form>
    @endforeach
</x-layouts.app>
