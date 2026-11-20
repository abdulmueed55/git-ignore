<x-layouts.app :title="$album->title" area="studio">
    <div class="mb-5 flex items-center gap-2 text-[12.5px] text-muted"><a href="{{ route('studio.gallery.index') }}" class="hover:text-ink">Gallery</a><x-icon name="chevron-right" :size="13" /><span class="text-ink">{{ $album->title }}</span></div>
    <x-ui.page-header eyebrow="{{ $album->taken_on?->format('j F Y') }} · {{ $album->images->count() }} photos" :title="$album->title" :description="$album->description">
        <x-slot:actions>
            <a href="{{ route('studio.gallery.edit', $album) }}" class="btn btn-ghost"><x-icon name="edit" :size="16" /> Edit details</a>
            <button form="del-album" class="btn btn-danger" onclick="return confirm('Delete this album and all its photos?')"><x-icon name="trash" :size="16" /> Delete</button>
        </x-slot:actions>
    </x-ui.page-header>
    <form id="del-album" method="POST" action="{{ route('studio.gallery.destroy', $album) }}" class="hidden">@csrf @method('DELETE')</form>

    <form method="POST" enctype="multipart/form-data" action="{{ route('studio.gallery.images.store', $album) }}" class="panel mb-6 flex flex-wrap items-center gap-3 p-4">
        @csrf
        <x-icon name="upload" class="text-brass" />
        <div class="min-w-0 flex-1 text-[13px]"><div class="font-semibold text-ink">Add more photos</div><div class="text-[12px] text-muted">Select several at once — they're added to the end of the album.</div></div>
        <input type="file" name="images[]" multiple required accept="image/jpeg,image/png,image/webp" class="input !h-auto max-w-xs py-1.5 text-[12.5px]">
        <button class="btn btn-primary">Upload</button>
        @error('images')<div class="field-error w-full">{{ $message }}</div>@enderror
        @error('images.*')<div class="field-error w-full">{{ $message }}</div>@enderror
    </form>

    @if ($album->images->isEmpty())
        <div class="panel"><x-ui.empty icon="images" title="This album has no photos" description="Upload photos above — the album stays hidden from visitors' view until it has some." /></div>
    @else
        <form method="POST" action="{{ route('studio.gallery.update', $album) }}">
            @csrf @method('PUT')
            <input type="hidden" name="title" value="{{ $album->title }}"><input type="hidden" name="description" value="{{ $album->description }}">
            <input type="hidden" name="taken_on" value="{{ $album->taken_on?->format('Y-m-d') }}">@if ($album->is_published)<input type="hidden" name="is_published" value="1">@endif
            <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 xl:grid-cols-4">
                @foreach ($album->images as $img)
                    <figure class="panel group overflow-hidden">
                        <div class="relative aspect-[3/2]">
                            <img src="{{ $img->url() }}" alt="{{ $img->caption }}" class="h-full w-full object-cover">
                            <button type="submit" form="del-img-{{ $img->id }}" class="absolute right-2 top-2 grid h-8 w-8 place-items-center rounded-md bg-black/55 text-white opacity-0 transition group-hover:opacity-100" title="Remove photo" onclick="return confirm('Remove this photo?')"><x-icon name="trash" :size="14" /></button>
                        </div>
                        <input name="captions[{{ $img->id }}]" value="{{ $img->caption }}" class="w-full border-0 border-t border-line bg-transparent px-3 py-2 text-[12.5px] text-ink outline-none focus:bg-sunken" placeholder="Add a caption…">
                    </figure>
                @endforeach
            </div>
            <div class="mt-5 flex justify-end"><button class="btn btn-primary">Save captions</button></div>
        </form>
        @foreach ($album->images as $img)
            <form id="del-img-{{ $img->id }}" method="POST" action="{{ route('studio.gallery.images.destroy', $img) }}" class="hidden">@csrf @method('DELETE')</form>
        @endforeach
    @endif
</x-layouts.app>
