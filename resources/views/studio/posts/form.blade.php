@php
    use App\Models\Post;
    $editing = $post->exists;
    $state = $editing ? $post->liveState() : null;
    $mode = old('publish_mode', match (true) {
        ! $editing => 'now',
        $post->status === 'draft' || $post->status === 'archived' => 'draft',
        $post->published_at?->isFuture() => 'schedule',
        default => 'now',
    });
    $dt = fn ($d) => $d?->format('Y-m-d\TH:i');
    $icons = ['announcement' => 'megaphone', 'news' => 'news', 'event' => 'calendar', 'notice' => 'pin'];
@endphp
<x-layouts.app :title="$editing ? 'Edit '.strtolower($post->typeLabel()) : 'New '.strtolower($post->typeLabel())" area="studio">
    <div class="mb-5 flex items-center gap-2 text-[12.5px] text-muted">
        <a href="{{ route('studio.posts.index', ['type' => $post->type]) }}" class="hover:text-ink">{{ \Illuminate\Support\Str::plural($post->typeLabel()) }}</a>
        <x-icon name="chevron-right" :size="13" />
        <span class="text-ink">{{ $editing ? \Illuminate\Support\Str::limit($post->title, 60) : 'New' }}</span>
    </div>

    <form method="POST" enctype="multipart/form-data"
          action="{{ $editing ? route('studio.posts.update', $post) : route('studio.posts.store') }}"
          x-data="postEditor({
              type: @js(old('type', $post->type)),
              title: @js(old('title', $post->title ?? '')),
              excerpt: @js(old('excerpt', $post->excerpt ?? '')),
              body: @js(old('body', $post->body ?? '')),
              mode: @js($mode),
              cover: @js($post->coverUrl()),
              venue: @js(old('venue', $post->venue ?? '')),
              starts: @js(old('event_starts_at', $dt($post->event_starts_at))),
          })"
          class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_340px]">
        @csrf
        @if ($editing) @method('PUT') @endif

        {{-- ================= Main column ================= --}}
        <div class="min-w-0 space-y-6">
            <section class="panel p-5 sm:p-6">
                {{-- Type --}}
                <div class="eyebrow mb-2">Type</div>
                <div class="grid grid-cols-2 gap-2 sm:grid-cols-4">
                    @foreach (Post::TYPES as $t => $l)
                        <label class="flex cursor-pointer items-center gap-2.5 rounded-lg border px-3 py-2.5 text-[13px] font-semibold transition"
                               :class="type === '{{ $t }}' ? 'border-brand bg-brand-soft text-brand' : 'border-line text-ink-2 hover:border-line-strong'">
                            <input type="radio" name="type" value="{{ $t }}" x-model="type" class="sr-only">
                            <x-icon :name="$icons[$t]" :size="16" /> {{ $l }}
                        </label>
                    @endforeach
                </div>
                @error('type')<div class="field-error">{{ $message }}</div>@enderror

                <div class="mt-6 space-y-5">
                    <div>
                        <label for="f_title" class="field-label">Headline <span class="req">*</span></label>
                        <input id="f_title" name="title" x-model="title" required maxlength="180" class="input !h-12 display !text-[19px]" @error('title') aria-invalid="true" @enderror placeholder="Write a clear, specific headline">
                        @error('title')<div class="field-error">{{ $message }}</div>@enderror
                        <div class="mt-1.5 flex flex-wrap items-center gap-2 text-[12px] text-muted">
                            <span>Web address:</span>
                            <span class="mono text-ink-2">/notices/</span>
                            <input name="slug" value="{{ old('slug', $post->slug) }}" class="mono rounded border border-transparent bg-transparent px-1 text-[12px] text-ink hover:border-line focus:border-brand focus:outline-none" :placeholder="slugify(title) || 'auto-from-headline'" size="40">
                        </div>
                        @error('slug')<div class="field-error">{{ $message }}</div>@enderror
                    </div>

                    <div>
                        <label for="f_excerpt" class="field-label">Summary <span class="req">*</span>
                            <span class="ml-auto mono font-normal" :class="excerpt.length > 380 ? 'text-warn' : 'text-muted'"><span x-text="excerpt.length"></span>/400</span>
                        </label>
                        <textarea id="f_excerpt" name="excerpt" x-model="excerpt" rows="2" maxlength="400" required class="input !min-h-[70px]" @error('excerpt') aria-invalid="true" @enderror placeholder="One or two sentences — shown in lists, the home page and link previews on WhatsApp/Facebook."></textarea>
                        @error('excerpt')<div class="field-error">{{ $message }}</div>@enderror
                    </div>

                    {{-- Body with toolbar + preview --}}
                    <div>
                        <div class="mb-1.5 flex items-center justify-between">
                            <label for="f_body" class="field-label !mb-0">Full text <span class="req">*</span></label>
                            <div class="tabs !p-0.5">
                                <button type="button" class="tab !h-7" :aria-selected="!preview" @click="preview=false">Write</button>
                                <button type="button" class="tab !h-7" :aria-selected="preview" @click="preview=true">Preview</button>
                            </div>
                        </div>
                        <div class="overflow-hidden rounded-[9px] border border-line-strong focus-within:border-brand @error('body') !border-bad @enderror">
                            <div x-show="!preview" class="flex flex-wrap gap-0.5 border-b border-line bg-sunken/60 px-1.5 py-1">
                                @foreach ([['B', 'strong', 'Bold', 'font-bold'], ['I', 'em', 'Italic', 'italic'], ['H2', 'h2', 'Heading', ''], ['H3', 'h3', 'Sub-heading', ''], ['¶', 'p', 'Paragraph', '']] as [$lbl, $tag, $tip, $cls])
                                    <button type="button" title="{{ $tip }}" @click="wrap('{{ $tag }}')" class="grid h-7 min-w-7 place-items-center rounded px-1.5 text-[12px] text-ink-2 hover:bg-surface hover:text-ink {{ $cls }}">{{ $lbl }}</button>
                                @endforeach
                                <span class="mx-1 my-1.5 w-px bg-line-strong"></span>
                                <button type="button" title="Bulleted list" @click="list()" class="grid h-7 w-7 place-items-center rounded text-ink-2 hover:bg-surface"><x-icon name="list" :size="15" /></button>
                                <button type="button" title="Link" @click="link()" class="grid h-7 w-7 place-items-center rounded text-ink-2 hover:bg-surface"><x-icon name="link" :size="15" /></button>
                                <span class="ml-auto hidden self-center pr-2 text-[11px] text-muted sm:inline">Plain text works too — blank line = new paragraph</span>
                            </div>
                            <textarea x-show="!preview" id="f_body" name="body" x-ref="body" x-model="body" rows="14" required class="block w-full resize-y bg-surface px-4 py-3 font-[inherit] text-[14px] leading-relaxed text-ink outline-none" placeholder="Write the complete text. Include dates, timings, who it applies to and whom to contact."></textarea>
                            <div x-show="preview" x-cloak class="prose-ledger min-h-[320px] bg-surface px-5 py-4" x-html="rendered()"></div>
                        </div>
                        @error('body')<div class="field-error">{{ $message }}</div>@enderror
                        <div class="field-hint">Scripts, styles and embedded frames are removed automatically when you save.</div>
                    </div>
                </div>
            </section>

            {{-- Event details --}}
            <section class="panel p-5 sm:p-6" x-show="type === 'event'" x-cloak x-transition.opacity>
                <div class="mb-4 flex items-center gap-2"><x-icon name="calendar" class="text-brass" /><h2 class="panel-title">Event details</h2><span class="text-[12px] text-muted">— required for events</span></div>
                <div class="grid gap-4 sm:grid-cols-3">
                    <x-ui.field name="event_starts_at" label="Starts" type="datetime-local" :value="$dt($post->event_starts_at)" x-bind:required="type==='event'" x-model="starts" />
                    <x-ui.field name="event_ends_at" label="Ends" type="datetime-local" :value="$dt($post->event_ends_at)" hint="Optional" />
                    <x-ui.field name="venue" label="Venue" :value="$post->venue" placeholder="e.g. College Auditorium" x-bind:required="type==='event'" x-model="venue" />
                </div>
            </section>

            {{-- Attachment --}}
            <section class="panel p-5 sm:p-6">
                <div class="mb-4 flex items-center gap-2"><x-icon name="file" class="text-brass" /><h2 class="panel-title">PDF attachment</h2><span class="text-[12px] text-muted">— date sheets, signed notifications, forms</span></div>
                @if ($post->attachment_path)
                    <div class="mb-4 flex flex-wrap items-center gap-3 rounded-lg border border-line bg-sunken/50 px-4 py-3 text-[13px]">
                        <span class="grid h-9 w-9 place-items-center rounded-md bg-bad-soft text-bad"><x-icon name="file" :size="16" /></span>
                        <a href="{{ $post->attachmentUrl() }}" target="_blank" class="font-semibold text-ink hover:text-brand">{{ $post->attachment_name }}</a>
                        <label class="ml-auto flex items-center gap-2 text-[12.5px] text-muted"><input type="checkbox" name="remove_attachment" value="1" class="checkbox"> Remove</label>
                    </div>
                @endif
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-ui.field name="attachment" label="{{ $post->attachment_path ? 'Replace PDF' : 'Upload PDF' }}" type="file" accept="application/pdf" hint="PDF up to 10 MB" class="[&_input]:!h-auto [&_input]:py-2" />
                    <x-ui.field name="attachment_name" label="Download name" :value="$post->attachment_name" placeholder="e.g. date-sheet-december.pdf" hint="What visitors see on the download button" />
                </div>
            </section>
        </div>

        {{-- ================= Sidebar ================= --}}
        <aside class="space-y-6 xl:sticky xl:top-[84px] xl:self-start">
            <section class="panel">
                <div class="panel-head">
                    <div class="panel-title">Publishing</div>
                    @if ($state)<x-ui.status :value="$state" />@else<span class="badge">New</span>@endif
                </div>
                <div class="space-y-4 p-5">
                    <div class="space-y-1.5">
                        @foreach (['now' => ['Publish now', 'Visible as soon as you save'], 'schedule' => ['Schedule', 'Goes live automatically'], 'draft' => ['Save as draft', 'Only staff can see it']] as $m => [$t, $d])
                            <label class="flex cursor-pointer items-start gap-3 rounded-lg border px-3 py-2.5" :class="mode==='{{ $m }}' ? 'border-brand bg-brand-soft/60' : 'border-line hover:border-line-strong'">
                                <input type="radio" name="publish_mode" value="{{ $m }}" x-model="mode" class="mt-0.5 accent-[var(--brand)]">
                                <span><span class="block text-[13px] font-semibold text-ink">{{ $t }}</span><span class="block text-[11.5px] text-muted">{{ $d }}</span></span>
                            </label>
                        @endforeach
                        @error('publish_mode')<div class="field-error">{{ $message }}</div>@enderror
                    </div>
                    <div x-show="mode==='schedule'" x-cloak>
                        <x-ui.field name="published_at" label="Go live at" type="datetime-local" :value="$dt($post->published_at && $post->published_at->isFuture() ? $post->published_at : null)" x-bind:required="mode==='schedule'" />
                    </div>
                    <x-ui.field name="expires_at" label="Hide after" type="datetime-local" :value="$dt($post->expires_at)" hint="Optional — e.g. after the deadline has passed" />
                    <div class="space-y-2 border-t border-line pt-4 text-[13px]">
                        <label class="flex items-center justify-between gap-2 text-ink-2"><span class="flex items-center gap-2"><x-icon name="pin" :size="14" class="text-brass" /> Pin to top of the home page</span><input type="checkbox" name="is_pinned" value="1" class="checkbox" @checked(old('is_pinned', $post->is_pinned))></label>
                        <label class="flex items-center justify-between gap-2 text-ink-2"><span class="flex items-center gap-2"><x-icon name="megaphone" :size="14" class="text-brass" /> Show in news ticker</span><input type="checkbox" name="show_in_ticker" value="1" class="checkbox" @checked(old('show_in_ticker', $post->show_in_ticker))></label>
                    </div>
                    <x-ui.field name="category" label="Category" type="select" required :options="array_combine($categories, $categories)" :value="$post->category" />
                </div>
                <div class="flex gap-2 border-t border-line p-4">
                    <button class="btn btn-primary flex-1" x-text="mode==='now' ? 'Save & publish' : (mode==='schedule' ? 'Save & schedule' : 'Save draft')">Save</button>
                    @if ($editing && $state === 'live')
                        <a href="{{ route('portal.post', $post->slug) }}" target="_blank" class="btn btn-ghost btn-icon" title="View on website"><x-icon name="arrow-up-right" :size="16" /></a>
                    @endif
                </div>
            </section>

            <section class="panel">
                <div class="panel-head"><div class="panel-title">Cover image</div><span class="text-[11.5px] text-muted">16:9 · JPG/PNG/WebP</span></div>
                <div class="p-5">
                    <label class="group relative block aspect-video cursor-pointer overflow-hidden rounded-lg border border-dashed border-line-strong bg-sunken/60 hover:border-brand">
                        <template x-if="cover"><img :src="cover" class="h-full w-full object-cover" alt=""></template>
                        <div x-show="!cover" class="absolute inset-0 grid place-items-center text-center text-[12.5px] text-muted">
                            <div><x-icon name="image" :size="26" class="mx-auto mb-2 text-brass" />Click to choose a cover<br><span class="text-[11px]">shown on the home page and in shares</span></div>
                        </div>
                        <input type="file" name="cover" accept="image/jpeg,image/png,image/webp" class="sr-only" @change="pickCover($event)">
                    </label>
                    @error('cover')<div class="field-error">{{ $message }}</div>@enderror
                    @if ($post->cover_path)
                        <label class="mt-2 flex items-center gap-2 text-[12.5px] text-muted"><input type="checkbox" name="remove_cover" value="1" class="checkbox"> Remove current cover</label>
                    @endif
                </div>
            </section>

            {{-- Live card preview --}}
            <section class="panel overflow-hidden">
                <div class="panel-head"><div class="panel-title">How it appears</div><span class="eyebrow">home page card</span></div>
                <div class="p-4">
                    <div class="overflow-hidden rounded-lg border border-line bg-paper">
                        <template x-if="cover"><img :src="cover" class="aspect-[16/8] w-full object-cover" alt=""></template>
                        <div class="p-3.5">
                            <div class="eyebrow !text-brass" x-text="({announcement:'Announcement',news:'News',event:'Event',notice:'Notice'})[type]"></div>
                            <div class="display mt-1 text-[16px] leading-snug text-ink" x-text="title || 'Your headline appears here'"></div>
                            <p class="mt-1.5 line-clamp-3 text-[12.5px] text-muted" x-text="excerpt || 'The summary appears under the headline.'"></p>
                            <div x-show="type==='event' && (starts || venue)" class="mt-2 text-[11.5px] font-semibold text-brand" x-text="[fmt(starts), venue].filter(Boolean).join(' · ')"></div>
                        </div>
                    </div>
                </div>
            </section>

            @if ($editing)
                <section class="panel p-4 text-[12px] text-muted">
                    <div class="flex justify-between"><span>Created</span><span class="text-ink-2">{{ $post->created_at->format('j M Y, g:i A') }}</span></div>
                    <div class="mt-1 flex justify-between"><span>Author</span><span class="text-ink-2">{{ $post->author?->name ?? '—' }}</span></div>
                    <div class="mt-1 flex justify-between"><span>Reads</span><span class="mono text-ink-2">{{ number_format($post->views) }}</span></div>
                    <div class="mt-3 flex flex-wrap gap-2 border-t border-line pt-3">
                        <button form="dup-form" class="btn btn-ghost btn-sm"><x-icon name="files" :size="14" /> Duplicate</button>
                        <button form="del-form" class="btn btn-danger btn-sm" data-confirm="Delete this permanently? It will disappear from the website."><x-icon name="trash" :size="14" /> Delete</button>
                    </div>
                </section>
            @endif
        </aside>
    </form>
    @if ($editing)
        <form id="dup-form" method="POST" action="{{ route('studio.posts.duplicate', $post) }}" class="hidden">@csrf</form>
        <form id="del-form" method="POST" action="{{ route('studio.posts.destroy', $post) }}" class="hidden">@csrf @method('DELETE')</form>
    @endif

    @push('scripts')
        <script @nonce>
            document.addEventListener('alpine:init', () => {
                Alpine.data('postEditor', (init) => ({
                    ...init,
                    preview: false,
                    slugify(s) { return (s || '').toLowerCase().normalize('NFKD').replace(/[^\w\s-]/g, '').trim().replace(/[\s_]+/g, '-').replace(/-+/g, '-').slice(0, 80); },
                    pickCover(e) { const f = e.target.files[0]; if (f) this.cover = URL.createObjectURL(f); },
                    fmt(v) { if (!v) return ''; const d = new Date(v); return isNaN(d) ? '' : d.toLocaleString('en-GB', { weekday: 'short', day: 'numeric', month: 'short', hour: 'numeric', minute: '2-digit' }); },
                    rendered() {
                        const b = this.body || '';
                        if (!/<[a-z][\s\S]*>/i.test(b)) {
                            const esc = (t) => t.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
                            return b.split(/\n{2,}/).filter((p) => p.trim()).map((p) => '<p>' + esc(p).replace(/\n/g, '<br>') + '</p>').join('') || '<p class="text-muted">Nothing written yet.</p>';
                        }
                        return window.safeHtml(b);
                    },
                    insert(before, after = '') {
                        const t = this.$refs.body, s = t.selectionStart, e = t.selectionEnd;
                        const sel = this.body.slice(s, e);
                        this.body = this.body.slice(0, s) + before + sel + after + this.body.slice(e);
                        this.$nextTick(() => { t.focus(); t.selectionStart = s + before.length; t.selectionEnd = s + before.length + sel.length; });
                    },
                    wrap(tag) { this.insert('<' + tag + '>', '</' + tag + '>'); },
                    list() {
                        const t = this.$refs.body, sel = this.body.slice(t.selectionStart, t.selectionEnd) || 'First item\nSecond item';
                        const html = '<ul>\n' + sel.split('\n').filter((l) => l.trim()).map((l) => '  <li>' + l.trim() + '</li>').join('\n') + '\n</ul>';
                        const s = t.selectionStart;
                        this.body = this.body.slice(0, s) + html + this.body.slice(t.selectionEnd);
                    },
                    link() { const url = prompt('Link address (https://…)'); if (url) this.insert('<a href="' + url.replace(/"/g, '') + '">', '</a>'); },
                }));
            });
        </script>
    @endpush
</x-layouts.app>
