<x-layouts.app :title="$page->exists ? 'Edit page' : 'New page'" area="studio">
    <div class="mb-5 flex items-center gap-2 text-[12.5px] text-muted">
        <a href="{{ route('studio.pages.index') }}" class="hover:text-ink">Pages</a><x-icon name="chevron-right" :size="13" /><span class="text-ink">{{ $page->title ?: 'New page' }}</span>
    </div>
    <form method="POST" action="{{ $page->exists ? route('studio.pages.update', $page) : route('studio.pages.store') }}" class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_320px]"
          x-data="{ preview: false, body: @js(old('body', $page->body ?? '')), summary: @js(old('summary', $page->summary ?? '')), meta: @js(old('meta_description', $page->meta_description ?? '')) }">
        @csrf @if ($page->exists) @method('PUT') @endif
        <section class="panel space-y-5 p-5 sm:p-6">
            <div>
                <label class="field-label" for="f_title">Page title <span class="req">*</span></label>
                <input id="f_title" name="title" value="{{ old('title', $page->title) }}" required class="input display !h-12 !text-[19px]" @error('title') aria-invalid="true" @enderror placeholder="e.g. Admission policy">
                @error('title')<div class="field-error">{{ $message }}</div>@enderror
            </div>
            <x-ui.field name="slug" label="Web address" :value="$page->slug" hint="Leave blank to create it from the title, e.g. /pages/admission-policy" placeholder="admission-policy" />
            <div>
                <label class="field-label" for="f_summary">Summary <span class="req">*</span><span class="ml-auto mono font-normal text-muted"><span x-text="summary.length"></span>/400</span></label>
                <textarea id="f_summary" name="summary" x-model="summary" rows="2" maxlength="400" required class="input !min-h-[64px]" @error('summary') aria-invalid="true" @enderror placeholder="Shown under the page title."></textarea>
                @error('summary')<div class="field-error">{{ $message }}</div>@enderror
            </div>
            <div>
                <div class="mb-1.5 flex items-center justify-between">
                    <label class="field-label !mb-0" for="f_body">Content <span class="req">*</span></label>
                    <div class="tabs !p-0.5"><button type="button" class="tab !h-7" :aria-selected="!preview" @click="preview=false">Write</button><button type="button" class="tab !h-7" :aria-selected="preview" @click="preview=true">Preview</button></div>
                </div>
                <textarea x-show="!preview" id="f_body" name="body" x-model="body" rows="18" required class="input mono !text-[12.5px]" @error('body') aria-invalid="true" @enderror placeholder="Plain text or simple HTML (<h3>, <p>, <ul>, <table>)."></textarea>
                <div x-show="preview" x-cloak class="prose-ledger min-h-[300px] rounded-[9px] border border-line p-5" x-html="window.safeHtml(body)"></div>
                @error('body')<div class="field-error">{{ $message }}</div>@enderror
            </div>
        </section>
        <aside class="space-y-6 xl:sticky xl:top-[84px] xl:self-start">
            <section class="panel">
                <div class="panel-head"><div class="panel-title">Visibility</div>@if ($page->exists)<x-ui.status :value="$page->is_published ? 'published' : 'draft'" :label="$page->is_published ? 'Published' : 'Hidden'" />@endif</div>
                <div class="space-y-4 p-5">
                    <label class="flex items-center justify-between text-[13px] text-ink-2">Published on the website <input type="checkbox" name="is_published" value="1" class="checkbox" @checked(old('is_published', $page->is_published))></label>
                    <label class="flex items-center justify-between text-[13px] text-ink-2">Show in the main menu <input type="checkbox" name="in_menu" value="1" class="checkbox" @checked(old('in_menu', $page->in_menu))></label>
                    <x-ui.field name="menu_order" label="Menu position" type="number" min="0" required :value="$page->menu_order" />
                </div>
                <div class="border-t border-line p-4"><button class="btn btn-primary w-full">Save page</button></div>
            </section>
            <section class="panel">
                <div class="panel-head"><div class="panel-title">Search & sharing</div></div>
                <div class="p-5">
                    <label class="field-label" for="f_meta">Meta description <span class="req">*</span><span class="ml-auto mono font-normal text-muted"><span x-text="meta.length"></span>/300</span></label>
                    <textarea id="f_meta" name="meta_description" x-model="meta" rows="3" maxlength="300" required class="input" @error('meta_description') aria-invalid="true" @enderror></textarea>
                    @error('meta_description')<div class="field-error">{{ $message }}</div>@enderror
                    <div class="mt-4 rounded-lg border border-line bg-paper p-3">
                        <div class="text-[11.5px] text-ok">fglacollege.edu.pk › pages</div>
                        <div class="mt-0.5 text-[14px] text-info">{{ $page->title ?: 'Page title' }} — F.G. Liaquat Ali Degree College</div>
                        <div class="mt-0.5 line-clamp-2 text-[12px] text-muted" x-text="meta || 'Write a description for search results.'"></div>
                    </div>
                </div>
            </section>
            @if ($page->exists)
                <button form="del-page" class="btn btn-danger btn-sm w-full" data-confirm="Delete this page? Links to it will stop working."><x-icon name="trash" :size="14" /> Delete page</button>
            @endif
        </aside>
    </form>
    @if ($page->exists)<form id="del-page" method="POST" action="{{ route('studio.pages.destroy', $page) }}" class="hidden">@csrf @method('DELETE')</form>@endif
</x-layouts.app>
