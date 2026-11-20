<x-layouts.app :title="$album->exists ? 'Edit album' : 'New album'" area="studio">
    <x-ui.page-header eyebrow="Gallery" :title="$album->exists ? 'Edit album' : 'New album'" description="Albums appear on the website gallery, newest first." />
    <form method="POST" enctype="multipart/form-data" action="{{ $album->exists ? route('studio.gallery.update', $album) : route('studio.gallery.store') }}" class="panel max-w-3xl space-y-5 p-5 sm:p-6"
          x-data="{ previews: [] }">
        @csrf @if ($album->exists) @method('PUT') @endif
        <x-ui.field name="title" label="Album title" required :value="$album->title" placeholder="e.g. Quaid-e-Azam Day 2026" />
        <x-ui.field name="description" label="Description" type="textarea" required rows="3" :value="$album->description" placeholder="What happened, who took part." />
        <div class="grid gap-4 sm:grid-cols-2">
            <x-ui.field name="taken_on" label="Date of the event" type="date" required :value="$album->taken_on?->format('Y-m-d')" max="{{ now()->format('Y-m-d') }}" />
            <label class="mt-7 flex items-center gap-2 text-[13px] text-ink-2"><input type="checkbox" name="is_published" value="1" class="checkbox" @checked(old('is_published', $album->is_published))> Show on the website</label>
        </div>
        @unless ($album->exists)
            <div>
                <label class="field-label">Photos <span class="req">*</span></label>
                <label class="flex cursor-pointer flex-col items-center justify-center rounded-lg border border-dashed border-line-strong bg-sunken/50 px-6 py-8 text-center text-[13px] text-muted hover:border-brand @error('images') !border-bad @enderror">
                    <x-icon name="upload" :size="24" class="mb-2 text-brass" />
                    Choose up to 30 photos <span class="text-[11.5px]">JPG, PNG or WebP · up to 6 MB each</span>
                    <input type="file" name="images[]" multiple accept="image/jpeg,image/png,image/webp" class="sr-only" required @change="previews = [...$event.target.files].map(f => URL.createObjectURL(f))">
                </label>
                @error('images')<div class="field-error">{{ $message }}</div>@enderror
                @error('images.*')<div class="field-error">{{ $message }}</div>@enderror
                <div x-show="previews.length" class="mt-3 grid grid-cols-4 gap-2 sm:grid-cols-6"><template x-for="p in previews"><img :src="p" class="aspect-square rounded-md object-cover"></template></div>
            </div>
        @endunless
        <div class="flex gap-2 border-t border-line pt-4">
            <button class="btn btn-primary">{{ $album->exists ? 'Save album' : 'Create album' }}</button>
            <a href="{{ $album->exists ? route('studio.gallery.show', $album) : route('studio.gallery.index') }}" class="btn btn-ghost">Cancel</a>
        </div>
    </form>
</x-layouts.app>
