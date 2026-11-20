<x-layouts.app title="Contact inbox" area="studio">
    <x-ui.page-header eyebrow="Inbox" title="Contact inbox" description="Messages sent through the website's contact form. Opening a message marks it as read." />
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <div class="tabs">
            <a href="{{ route('studio.messages.index') }}" class="tab" aria-selected="{{ $filter !== 'unread' ? 'true' : 'false' }}">All <span class="mono text-[10.5px] opacity-70">{{ $total }}</span></a>
            <a href="{{ route('studio.messages.index', ['filter' => 'unread']) }}" class="tab" aria-selected="{{ $filter === 'unread' ? 'true' : 'false' }}">Unread <span class="mono text-[10.5px] opacity-70">{{ $unreadCount }}</span></a>
        </div>
        <form method="GET" class="relative">
            <input type="hidden" name="filter" value="{{ $filter }}">
            <x-icon name="search" :size="15" class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-muted" />
            <input name="q" value="{{ $q }}" class="input !h-9 !w-64 !pl-9 !text-[12.5px]" placeholder="Search name, subject or text">
        </form>
    </div>
    <div class="panel overflow-hidden">
        @forelse ($messages as $m)
            <a href="{{ route('studio.messages.show', $m) }}" class="flex items-start gap-4 border-b border-line px-5 py-4 last:border-0 hover:bg-sunken/50">
                <span class="mt-2 h-2 w-2 shrink-0 rounded-full {{ $m->read_at ? 'bg-transparent' : 'bg-brass' }}"></span>
                <x-ui.avatar :name="$m->name" :size="36" />
                <div class="min-w-0 flex-1">
                    <div class="flex items-baseline justify-between gap-3">
                        <span class="truncate text-[13.5px] {{ $m->read_at ? 'text-ink-2' : 'font-semibold text-ink' }}">{{ $m->name }}</span>
                        <span class="shrink-0 text-[11.5px] text-muted">{{ $m->created_at->format('j M, g:i A') }}</span>
                    </div>
                    <div class="truncate text-[13px] {{ $m->read_at ? 'text-ink-2' : 'font-semibold text-ink' }}">{{ $m->subject }}</div>
                    <div class="truncate text-[12.5px] text-muted">{{ $m->message }}</div>
                </div>
            </a>
        @empty
            <x-ui.empty icon="mail" title="{{ $filter === 'unread' ? 'All caught up' : 'No messages' }}" description="{{ $filter === 'unread' ? 'Every message has been read.' : 'Messages from the website contact form will appear here.' }}" />
        @endforelse
        @if ($messages->hasPages())<div class="border-t border-line px-4 py-3">{{ $messages->links() }}</div>@endif
    </div>
</x-layouts.app>
