<x-layouts.app title="Pages" area="studio">
    <x-ui.page-header eyebrow="Site content" title="Pages" description="Permanent pages such as About, Admission policy and Fee structure. Pages marked “In menu” form the website's main navigation, in this order.">
        <x-slot:actions>
            <a href="{{ route('studio.pages.create') }}" class="btn btn-primary"><x-icon name="plus" :size="16" /> New page</a>
        </x-slot:actions>
    </x-ui.page-header>

    <div class="grid gap-6 xl:grid-cols-[1fr_300px]">
        <div class="panel overflow-hidden">
            @if ($pages->isEmpty())
                <x-ui.empty icon="file" title="No pages yet" description="Start with “About the college” and “Admission policy” — visitors look for these first.">
                    <a href="{{ route('studio.pages.create') }}" class="btn btn-primary btn-sm">Create a page</a>
                </x-ui.empty>
            @else
                <div class="overflow-x-auto">
                    <table class="table">
                        <thead><tr><th class="w-12">#</th><th>Page</th><th>Address</th><th>Visibility</th><th>Last edited</th><th></th></tr></thead>
                        <tbody>
                            @foreach ($pages as $page)
                                <tr>
                                    <td class="mono text-muted">{{ $page->in_menu ? str_pad($page->menu_order, 2, '0', STR_PAD_LEFT) : '—' }}</td>
                                    <td class="max-w-[380px]">
                                        <a href="{{ route('studio.pages.edit', $page) }}" class="font-semibold text-ink hover:text-brand">{{ $page->title }}</a>
                                        <div class="truncate text-[12px] text-muted">{{ $page->summary }}</div>
                                    </td>
                                    <td class="mono text-[12px]">/pages/{{ $page->slug }}</td>
                                    <td>
                                        <div class="flex flex-wrap gap-1.5">
                                            <x-ui.status :value="$page->is_published ? 'published' : 'draft'" :label="$page->is_published ? 'Published' : 'Hidden'" />
                                            @if ($page->in_menu)<span class="badge badge-plain">In menu</span>@endif
                                        </div>
                                    </td>
                                    <td class="whitespace-nowrap text-[12.5px]">{{ $page->updated_at->diffForHumans() }}<div class="text-[11.5px] text-muted">{{ $page->editor?->name ?? 'Office' }}</div></td>
                                    <td class="text-right whitespace-nowrap">
                                        @if ($page->is_published)<a href="{{ route('portal.page', $page->slug) }}" target="_blank" class="btn btn-ghost btn-sm btn-icon !w-8" title="View"><x-icon name="arrow-up-right" :size="14" /></a>@endif
                                        <a href="{{ route('studio.pages.edit', $page) }}" class="btn btn-ghost btn-sm">Edit</a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        <aside class="panel h-max">
            <div class="panel-head"><div class="panel-title">Main menu preview</div></div>
            <ol class="space-y-1 p-4 text-[13px]">
                @forelse ($pages->where('in_menu', true)->where('is_published', true) as $p)
                    <li class="flex items-center gap-2 rounded-md px-2 py-1.5 hover:bg-sunken"><span class="mono w-5 text-[11px] text-muted">{{ $loop->iteration }}</span>{{ $p->title }}</li>
                @empty
                    <li class="text-muted">No pages are in the menu yet.</li>
                @endforelse
            </ol>
            <p class="border-t border-line px-4 py-3 text-[12px] text-muted">Change the order with the “Menu position” number on each page.</p>
        </aside>
    </div>
</x-layouts.app>
