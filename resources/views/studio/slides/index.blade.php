<x-layouts.app title="Hero slides" area="studio">
    <x-ui.page-header eyebrow="Site content" title="Hero slides" description="The large rotating banner at the top of the home page. Drag to reorder; the first active slide shows first.">
        <x-slot:actions><a href="{{ route('studio.slides.create') }}" class="btn btn-primary"><x-icon name="plus" :size="16" /> Add slide</a></x-slot:actions>
    </x-ui.page-header>

    @if ($slides->isEmpty())
        <div class="panel"><x-ui.empty icon="image" title="No slides" description="The home page banner is empty. Add a wide image (at least 1000 × 400) with a headline.">
            <a href="{{ route('studio.slides.create') }}" class="btn btn-primary btn-sm">Add the first slide</a>
        </x-ui.empty></div>
    @else
        <form method="POST" action="{{ route('studio.slides.reorder') }}" x-data="{ dragging: null, changed: false }">
            @csrf
            <div class="space-y-4" x-ref="list">
                @foreach ($slides as $slide)
                    <div class="panel flex flex-col overflow-hidden sm:flex-row" draggable="true"
                         @dragstart="dragging = $el" @dragend="dragging = null"
                         @dragover.prevent="if (dragging && dragging !== $el) { const r = $el.getBoundingClientRect(); $refs.list.insertBefore(dragging, (event.clientY - r.top) > r.height / 2 ? $el.nextSibling : $el); changed = true }">
                        <input type="hidden" name="order[]" value="{{ $slide->id }}">
                        <div class="relative aspect-[5/2] shrink-0 sm:w-[420px]">
                            <img src="{{ $slide->imageUrl() }}" alt="" class="h-full w-full object-cover {{ $slide->is_active ? '' : 'grayscale opacity-60' }}">
                            <div class="absolute inset-0 bg-gradient-to-t from-black/60 to-transparent"></div>
                            <div class="absolute bottom-3 left-4 right-4 text-white">
                                <div class="display text-[17px] leading-tight">{{ $slide->heading }}</div>
                                @if ($slide->cta_label)<span class="mt-2 inline-block rounded bg-white/90 px-2 py-0.5 text-[11px] font-semibold text-black">{{ $slide->cta_label }} →</span>@endif
                            </div>
                        </div>
                        <div class="flex flex-1 items-start gap-4 p-5">
                            <span class="cursor-grab text-muted" title="Drag to reorder"><x-icon name="menu" /></span>
                            <div class="min-w-0 flex-1">
                                <div class="flex items-center gap-2"><span class="mono text-[12px] text-muted">#{{ $loop->iteration }}</span><x-ui.status :value="$slide->is_active ? 'live' : 'draft'" :label="$slide->is_active ? 'Showing' : 'Hidden'" /></div>
                                <div class="mt-2 text-[14px] font-semibold text-ink">{{ $slide->heading }}</div>
                                <p class="mt-1 text-[13px] text-muted">{{ $slide->subheading }}</p>
                                @if ($slide->cta_url)<div class="mono mt-2 text-[12px] text-brand">{{ $slide->cta_url }}</div>@endif
                            </div>
                            <a href="{{ route('studio.slides.edit', $slide) }}" class="btn btn-ghost btn-sm">Edit</a>
                        </div>
                    </div>
                @endforeach
            </div>
            <div x-show="changed" x-cloak class="sticky bottom-4 mt-4 flex justify-end"><button class="btn btn-brass shadow-lg">Save new order</button></div>
        </form>
    @endif
</x-layouts.app>
