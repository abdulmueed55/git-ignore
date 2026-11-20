<x-layouts.app title="Gallery" area="studio">
    <x-ui.page-header eyebrow="Site content" title="Gallery" description="Photo albums of events and campus life. Upload several photos at once; captions are optional.">
        <x-slot:actions><a href="{{ route('studio.gallery.create') }}" class="btn btn-primary"><x-icon name="plus" :size="16" /> New album</a></x-slot:actions>
    </x-ui.page-header>
    @if ($albums->isEmpty())
        <div class="panel"><x-ui.empty icon="images" title="No albums yet" description="Create an album for your next event and upload the photos in one go.">
            <a href="{{ route('studio.gallery.create') }}" class="btn btn-primary btn-sm">Create an album</a>
        </x-ui.empty></div>
    @else
        <div class="grid gap-5 sm:grid-cols-2 xl:grid-cols-3">
            @foreach ($albums as $album)
                <a href="{{ route('studio.gallery.show', $album) }}" class="panel group overflow-hidden hover:border-line-strong">
                    <div class="grid aspect-[16/10] grid-cols-3 grid-rows-2 gap-0.5 bg-sunken">
                        @foreach ($album->images->take(3) as $img)
                            <img src="{{ $img->url() }}" alt="" class="h-full w-full object-cover {{ $loop->first ? 'col-span-2 row-span-2' : '' }} transition group-hover:opacity-90">
                        @endforeach
                    </div>
                    <div class="p-4">
                        <div class="flex items-center justify-between gap-2">
                            <div class="truncate text-[14px] font-semibold text-ink">{{ $album->title }}</div>
                            <x-ui.status :value="$album->is_published ? 'published' : 'draft'" :label="$album->is_published ? 'Public' : 'Hidden'" />
                        </div>
                        <div class="mt-1 flex gap-3 text-[12px] text-muted">
                            <span>{{ $album->taken_on?->format('j M Y') }}</span><span>·</span><span class="mono">{{ $album->images_count }} photos</span>
                        </div>
                    </div>
                </a>
            @endforeach
        </div>
    @endif
</x-layouts.app>
