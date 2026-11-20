<x-layouts.app :title="$message->subject" area="studio">
    <div class="mb-5 flex items-center justify-between gap-2 text-[12.5px] text-muted">
        <div class="flex items-center gap-2"><a href="{{ route('studio.messages.index') }}" class="hover:text-ink">Contact inbox</a><x-icon name="chevron-right" :size="13" /><span class="text-ink">Message</span></div>
        <div class="flex gap-1">
            @if ($previous)<a href="{{ route('studio.messages.show', $previous) }}" class="btn btn-ghost btn-sm"><x-icon name="arrow-left" :size="14" /> Older</a>@endif
            @if ($next)<a href="{{ route('studio.messages.show', $next) }}" class="btn btn-ghost btn-sm">Newer <x-icon name="arrow-right" :size="14" /></a>@endif
        </div>
    </div>
    <article class="panel max-w-3xl">
        <header class="flex flex-wrap items-start gap-4 border-b border-line p-6">
            <x-ui.avatar :name="$message->name" :size="44" />
            <div class="min-w-0 flex-1">
                <h1 class="display text-[24px] leading-tight text-ink">{{ $message->subject }}</h1>
                <div class="mt-1 text-[13px] text-ink-2">{{ $message->name }}</div>
                <div class="mt-1 flex flex-wrap gap-x-4 gap-y-1 text-[12.5px] text-muted">
                    @if ($message->email)<a href="mailto:{{ $message->email }}" class="inline-flex items-center gap-1 hover:text-brand"><x-icon name="mail" :size="13" />{{ $message->email }}</a>@endif
                    @if ($message->phone)<a href="tel:{{ preg_replace('/[^0-9+]/', '', $message->phone) }}" class="mono inline-flex items-center gap-1 hover:text-brand"><x-icon name="phone" :size="13" />{{ $message->phone }}</a>@endif
                    <span>{{ $message->created_at->format('l j F Y, g:i A') }}</span>
                </div>
            </div>
        </header>
        <div class="prose-ledger whitespace-pre-line p-6">{{ $message->message }}</div>
        <footer class="flex flex-wrap gap-2 border-t border-line p-4">
            @if ($message->email)<a href="mailto:{{ $message->email }}?subject={{ rawurlencode('Re: '.$message->subject) }}" class="btn btn-primary"><x-icon name="mail" :size="16" /> Reply by email</a>@endif
            @if ($message->phone)<a href="https://wa.me/92{{ ltrim(preg_replace('/\D/', '', $message->phone), '0') }}" target="_blank" class="btn btn-ghost"><x-icon name="phone" :size="16" /> WhatsApp</a>@endif
            <form method="POST" action="{{ route('studio.messages.unread', $message) }}">@csrf @method('PATCH')<button class="btn btn-ghost">Mark unread</button></form>
            <form method="POST" action="{{ route('studio.messages.destroy', $message) }}" class="ml-auto" onsubmit="return confirm('Delete this message?')">@csrf @method('DELETE')<button class="btn btn-danger"><x-icon name="trash" :size="16" /> Delete</button></form>
        </footer>
    </article>
</x-layouts.app>
