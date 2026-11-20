<x-layouts.app title="Faculty directory" area="studio">
    <x-ui.page-header eyebrow="Site content" title="Faculty directory" description="Public profiles of teaching staff. Import staff accounts from the ERP, then complete qualifications and publish.">
        <x-slot:actions>
            <form method="POST" action="{{ route('studio.faculty.import') }}">@csrf
                <button class="btn btn-ghost" @disabled(! $importable)><x-icon name="users" :size="16" /> Import from staff @if ($importable)<span class="badge badge-brass badge-plain !h-5">{{ $importable }}</span>@endif</button>
            </form>
            <a href="{{ route('studio.faculty.create') }}" class="btn btn-primary"><x-icon name="plus" :size="16" /> Add profile</a>
        </x-slot:actions>
    </x-ui.page-header>

    <form method="GET" class="mb-4 flex items-center gap-2">
        <select name="department" class="input !h-9 !w-auto !text-[12.5px]" onchange="this.form.submit()">
            <option value="">All departments</option>
            @foreach ($departments as $d)<option value="{{ $d->id }}" @selected($department == $d->id)>{{ $d->name }}</option>@endforeach
        </select>
        <span class="text-[12.5px] text-muted">{{ $profiles->count() }} profiles · {{ $profiles->where('is_published', true)->count() }} public</span>
    </form>

    @if ($profiles->isEmpty())
        <div class="panel"><x-ui.empty icon="id" title="No faculty profiles" description="Import your teaching staff from the ERP in one click — profiles start hidden until you complete them.">
            <form method="POST" action="{{ route('studio.faculty.import') }}">@csrf<button class="btn btn-primary btn-sm">Import staff</button></form>
        </x-ui.empty></div>
    @else
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            @foreach ($profiles as $p)
                @php $incomplete = ! $p->qualification || ! $p->specialization; @endphp
                <a href="{{ route('studio.faculty.edit', $p) }}" class="panel group flex gap-4 p-4 hover:border-line-strong {{ $p->is_published ? '' : 'opacity-75' }}">
                    @if ($p->photoUrl())
                        <img src="{{ $p->photoUrl() }}" alt="" class="h-14 w-14 shrink-0 rounded-full object-cover">
                    @else
                        <x-ui.avatar :name="$p->name" :size="56" />
                    @endif
                    <div class="min-w-0 flex-1">
                        <div class="truncate text-[14px] font-semibold text-ink group-hover:text-brand">{{ $p->name }}</div>
                        <div class="text-[12.5px] text-ink-2">{{ $p->designation }} · {{ $p->department?->name ?? 'No department' }}</div>
                        <div class="mt-1 truncate text-[12px] text-muted">{{ $p->qualification ?? 'Qualification missing' }}{{ $p->specialization ? ' · '.$p->specialization : '' }}</div>
                        <div class="mt-2 flex flex-wrap gap-1.5">
                            @unless ($p->is_published)<span class="badge badge-warn">Hidden</span>@endunless
                            @if ($incomplete)<span class="badge badge-warn badge-plain">Incomplete</span>@endif
                            @if ($p->user_id)<span class="badge badge-plain">Linked to staff</span>@endif
                        </div>
                    </div>
                </a>
            @endforeach
        </div>
    @endif
</x-layouts.app>
