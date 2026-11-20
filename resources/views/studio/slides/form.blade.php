<x-layouts.app :title="$slide->exists ? 'Edit slide' : 'Add slide'" area="studio">
    <x-ui.page-header eyebrow="Hero slides" :title="$slide->exists ? 'Edit slide' : 'Add a slide'" description="Use a wide landscape image. Text is placed over the darker lower-left area." />
    <form method="POST" enctype="multipart/form-data" action="{{ $slide->exists ? route('studio.slides.update', $slide) : route('studio.slides.store') }}"
          class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_minmax(0,1.1fr)]"
          x-data="{ img: @js($slide->exists ? $slide->imageUrl() : null), h: @js(old('heading', $slide->heading ?? '')), s: @js(old('subheading', $slide->subheading ?? '')), c: @js(old('cta_label', $slide->cta_label ?? '')) }">
        @csrf @if ($slide->exists) @method('PUT') @endif
        <section class="panel space-y-4 p-5 sm:p-6">
            <x-ui.field name="heading" label="Headline" required :value="$slide->heading" x-model="h" maxlength="120" />
            <x-ui.field name="subheading" label="Supporting line" required :value="$slide->subheading" x-model="s" maxlength="220" />
            <div>
                <label class="field-label">Image @unless ($slide->exists)<span class="req">*</span>@endunless</label>
                <input type="file" name="image" accept="image/jpeg,image/png,image/webp" class="input !h-auto py-2" @error('image') aria-invalid="true" @enderror @change="const f=$event.target.files[0]; if(f) img=URL.createObjectURL(f)" @unless($slide->exists) required @endunless>
                @error('image')<div class="field-error">{{ $message }}</div>@else<div class="field-hint">JPG, PNG or WebP · at least 1000 × 400 px · up to 5 MB</div>@enderror
            </div>
            <div class="grid gap-4 sm:grid-cols-2">
                <x-ui.field name="cta_label" label="Button label" :value="$slide->cta_label" x-model="c" placeholder="e.g. Apply now" hint="Optional" />
                <x-ui.field name="cta_url" label="Button link" :value="$slide->cta_url" placeholder="/apply or https://…" />
            </div>
            <div class="grid gap-4 sm:grid-cols-2">
                <x-ui.field name="sort_order" label="Position" type="number" min="0" required :value="$slide->sort_order" />
                <label class="mt-7 flex items-center gap-2 text-[13px] text-ink-2"><input type="checkbox" name="is_active" value="1" class="checkbox" @checked(old('is_active', $slide->is_active))> Show on the home page</label>
            </div>
            <div class="flex flex-wrap gap-2 border-t border-line pt-4">
                <button class="btn btn-primary">{{ $slide->exists ? 'Save slide' : 'Add slide' }}</button>
                <a href="{{ route('studio.slides.index') }}" class="btn btn-ghost">Cancel</a>
                @if ($slide->exists)<button form="del-slide" class="btn btn-danger ml-auto" onclick="return confirm('Remove this slide?')">Remove</button>@endif
            </div>
        </section>
        <section>
            <div class="eyebrow mb-2">Preview</div>
            <div class="relative aspect-[5/2] overflow-hidden rounded-xl border border-line bg-rail">
                <template x-if="img"><img :src="img" class="absolute inset-0 h-full w-full object-cover" alt=""></template>
                <div class="absolute inset-0 bg-gradient-to-tr from-black/70 via-black/20 to-transparent"></div>
                <div class="absolute bottom-[10%] left-[6%] right-[30%] text-white">
                    <div class="display text-[clamp(16px,2.4vw,28px)] leading-tight" x-text="h || 'Your headline'"></div>
                    <div class="mt-1.5 text-[clamp(11px,1.1vw,14px)] text-white/80" x-text="s || 'Supporting line'"></div>
                    <span x-show="c" class="mt-3 inline-block rounded-md bg-[#d4a64a] px-3 py-1.5 text-[12px] font-semibold text-black" x-text="c + ' →'"></span>
                </div>
            </div>
        </section>
    </form>
    @if ($slide->exists)<form id="del-slide" method="POST" action="{{ route('studio.slides.destroy', $slide) }}" class="hidden">@csrf @method('DELETE')</form>@endif
</x-layouts.app>
