@php
    $types = array_values(array_unique(array_merge($required, ['character_certificate'])));
    $initial = $app->documents->mapWithKeys(fn ($d) => [$d->type => [
        'status' => $d->extraction_status, 'name' => $d->original_name, 'confidence' => $d->confidence, 'error' => $d->extraction_error,
        'summary' => $d->extraction_status === 'done' ? collect($d->extracted ?? [])->only(['name', 'father_name', 'cnic_number', 'date_of_birth', 'roll_no', 'obtained', 'total', 'board', 'district'])->filter()->mapWithKeys(fn ($v, $k) => [str_replace('_', ' ', $k) => (string) $v])->all() : [],
        'issues' => $d->extracted['issues'] ?? [],
        'preview' => $d->isImage() ? route('apply.file', [$app->tracking_no, $d->type]).'?v='.$d->updated_at?->timestamp : null,
    ]]);
    $hints = [
        'cnic' => 'Front side. B-Form if you are under 18.',
        'father_cnic' => 'Front side of your father\'s (or guardian\'s) CNIC.',
        'matric_dmc' => 'SSC result card / DMC with all marks visible.',
        'inter_dmc' => 'HSSC result card / DMC with all marks visible.',
        'photo' => 'Recent, blue background, face clearly visible.',
        'domicile' => 'Full page, district name readable.',
        'character_certificate' => 'From your last school or college.',
    ];
@endphp
<x-admissions.public title="Upload documents" :step="2">
    @push('alpine')
    <script @nonce>
        document.addEventListener('alpine:init', () => {
            Alpine.data('docsPage', (cfg) => ({
                docs: cfg.initial,
                required: cfg.required,
                timer: null,
                init() { if (this.busy()) this.poll(); },
                busy() { return Object.values(this.docs).some(d => ['pending', 'processing', 'uploading'].includes(d.status)); },
                complete() { return this.required.every(t => this.docs[t] && this.docs[t].status !== 'uploading'); },
                done() { return this.required.filter(t => this.docs[t] && this.docs[t].status !== 'uploading').length; },
                poll() {
                    clearTimeout(this.timer);
                    this.timer = setTimeout(async () => {
                        try {
                            const r = await fetch(cfg.progressUrl, { headers: { Accept: 'application/json' } });
                            const j = await r.json();
                            for (const [t, d] of Object.entries(j.documents)) {
                                if (this.docs[t]?.status !== 'uploading') this.docs[t] = d;
                            }
                        } catch (e) {}
                        if (this.busy()) this.poll();
                    }, 1500);
                },
                upload(type, file) {
                    if (!file) return;
                    const limit = (type === 'photo' ? 2 : 5) * 1024 * 1024;
                    if (file.size > limit) { this.docs[type] = { status: 'failed', error: 'File is too large — keep it under ' + (limit / 1048576) + ' MB.', summary: {} }; return; }
                    const preview = file.type.startsWith('image/') ? URL.createObjectURL(file) : null;
                    this.docs[type] = { status: 'uploading', name: file.name, progress: 0, preview, summary: {} };
                    const fd = new FormData();
                    fd.append('file', file);
                    const xhr = new XMLHttpRequest();
                    xhr.open('POST', cfg.uploadUrl.replace('__TYPE__', type));
                    xhr.setRequestHeader('X-CSRF-TOKEN', document.querySelector('meta[name=csrf-token]').content);
                    xhr.setRequestHeader('Accept', 'application/json');
                    xhr.upload.onprogress = (e) => { if (e.lengthComputable) this.docs[type].progress = Math.round(e.loaded / e.total * 100); };
                    xhr.onload = () => {
                        let j = {};
                        try { j = JSON.parse(xhr.responseText); } catch (e) {}
                        if (xhr.status >= 200 && xhr.status < 300) {
                            this.docs[type] = { ...j.document, preview: preview || j.document.preview };
                            this.poll();
                        } else {
                            const msg = j.errors?.file?.[0] ?? j.message ?? 'Upload failed — please try again.';
                            this.docs[type] = { status: 'failed', error: msg, summary: {}, name: file.name, preview };
                        }
                    };
                    xhr.onerror = () => { this.docs[type] = { status: 'failed', error: 'Network error — check your connection and retry.', summary: {} }; };
                    xhr.send(fd);
                },
            }));
        });
    </script>
    @endpush

    @if (session('pin_notice'))
        <div class="mb-6 flex flex-wrap items-center gap-4 rounded-2xl border border-brass/40 bg-brass-soft px-5 py-4">
            <x-icon name="lock" :size="20" class="text-brass" />
            <div class="flex-1 text-[13.5px] text-ink">Save these to continue later or check your status:</div>
            <div class="mono rounded-lg bg-surface px-3 py-1.5 text-[14px] font-semibold text-ink">{{ $app->tracking_no }}</div>
            <div class="mono rounded-lg bg-surface px-3 py-1.5 text-[14px] font-semibold text-ink">PIN {{ \App\Http\Controllers\Apply\ApplyController::knownPin(request(), $app) ?? '••••••' }}</div>
        </div>
    @endif

    <div x-data="docsPage(@js([
        'initial' => (object) $initial->all(),
        'required' => $required,
        'uploadUrl' => route('apply.upload', [$app->tracking_no, '__TYPE__']),
        'progressUrl' => route('apply.progress', $app->tracking_no),
    ]))">
        <div class="mb-6 flex flex-wrap items-end justify-between gap-4">
            <div>
                <div class="eyebrow">Step 2 of 3 · {{ $app->program?->name }}</div>
                <h1 class="display mt-1 text-[32px] text-ink">Upload your documents</h1>
                <p class="mt-1 max-w-2xl text-[13.5px] text-muted">
                    @if ($aiEnabled)
                        Each file is read automatically — names, CNIC numbers, dates and marks fill your form for you. You'll check everything on the next step.
                    @else
                        Upload clear photos or PDFs. You'll type the details on the next step and the admission office will check them against these files.
                    @endif
                </p>
            </div>
            <div class="text-right">
                <div class="numeral text-[30px] text-ink"><span x-text="done()"></span><span class="text-[16px] text-muted">/{{ count($required) }}</span></div>
                <div class="eyebrow !text-[9.5px]">required uploaded</div>
            </div>
        </div>

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($types as $type)
                <div class="panel flex flex-col overflow-hidden" x-data="{ over: false }"
                     :class="over ? '!border-brand ring-2 ring-brand/20' : ''"
                     @dragover.prevent="over = true" @dragleave.prevent="over = false"
                     @drop.prevent="over = false; upload('{{ $type }}', $event.dataTransfer.files[0])">
                    <div class="flex items-start justify-between gap-2 p-4 pb-3">
                        <div>
                            <div class="text-[13.5px] font-semibold text-ink">{{ \App\Models\AdmissionApplication::DOCUMENT_TYPES[$type] }}
                                @if (in_array($type, $required))<span class="text-bad">*</span>@else<span class="text-[11px] font-normal text-muted">optional</span>@endif
                            </div>
                            <div class="mt-0.5 text-[12px] text-muted">{{ $hints[$type] }}</div>
                        </div>
                        <template x-if="docs['{{ $type }}']?.status === 'done'"><span class="badge badge-ok badge-plain"><x-icon name="check" :size="11" /> Read</span></template>
                        <template x-if="docs['{{ $type }}']?.status === 'skipped'"><span class="badge badge-ok badge-plain"><x-icon name="check" :size="11" /> Saved</span></template>
                        <template x-if="docs['{{ $type }}']?.status === 'failed'"><span class="badge badge-bad badge-plain">Retry</span></template>
                    </div>

                    <label class="relative mx-4 mb-4 flex min-h-[150px] flex-1 cursor-pointer flex-col items-center justify-center overflow-hidden rounded-xl border border-dashed border-line-strong bg-paper text-center transition hover:border-brand">
                        <input type="file" class="sr-only" accept="{{ $type === 'photo' ? 'image/jpeg,image/png' : 'image/*,application/pdf' }}" @change="upload('{{ $type }}', $event.target.files[0]); $event.target.value = ''">

                        {{-- empty --}}
                        <template x-if="!docs['{{ $type }}']">
                            <div class="p-4">
                                <x-icon name="upload" :size="22" class="mx-auto text-brass" />
                                <div class="mt-2 text-[13px] font-semibold text-ink">Drop file or tap to choose</div>
                                <div class="text-[11.5px] text-muted">{{ $type === 'photo' ? 'JPG / PNG · up to 2 MB' : 'JPG, PNG or PDF · up to 5 MB' }}</div>
                            </div>
                        </template>

                        {{-- uploading / reading --}}
                        <template x-if="['uploading','pending','processing'].includes(docs['{{ $type }}']?.status)">
                            <div class="absolute inset-0">
                                <template x-if="docs['{{ $type }}'].preview"><img :src="docs['{{ $type }}'].preview" class="h-full w-full object-cover opacity-60" alt=""></template>
                                <div class="absolute inset-0 overflow-hidden">
                                    <div class="scan-line h-1/4 w-full bg-gradient-to-b from-transparent via-brass/40 to-transparent"></div>
                                </div>
                                <div class="absolute inset-x-3 bottom-3 rounded-lg bg-surface/95 px-3 py-2 text-left shadow">
                                    <div class="flex items-center gap-2 text-[12.5px] font-semibold text-ink">
                                        <x-icon name="cpu" :size="14" class="text-brass" />
                                        <span x-text="docs['{{ $type }}'].status === 'uploading' ? 'Uploading ' + (docs['{{ $type }}'].progress ?? 0) + '%' : '{{ $type === 'photo' ? 'Saving…' : 'AI is reading…' }}'"></span>
                                    </div>
                                    <div class="mt-1.5 h-1 overflow-hidden rounded-full bg-sunken"><div class="h-full rounded-full bg-brass transition-all" :style="'width:' + (docs['{{ $type }}'].status === 'uploading' ? (docs['{{ $type }}'].progress ?? 0) : 100) + '%'"></div></div>
                                </div>
                            </div>
                        </template>

                        {{-- read / saved --}}
                        <template x-if="['done','skipped'].includes(docs['{{ $type }}']?.status)">
                            <div class="w-full self-stretch p-3 text-left">
                                <div class="flex items-center gap-2.5 text-[12px] text-muted">
                                    <template x-if="docs['{{ $type }}'].preview"><img :src="docs['{{ $type }}'].preview" alt="" class="h-11 w-14 shrink-0 rounded-md border border-line object-cover"></template>
                                    <template x-if="!docs['{{ $type }}'].preview"><span class="grid h-11 w-14 shrink-0 place-items-center rounded-md bg-sunken"><x-icon name="file" :size="16" /></span></template>
                                    <span class="truncate" x-text="docs['{{ $type }}'].name"></span>
                                </div>
                                <template x-if="Object.keys(docs['{{ $type }}'].summary || {}).length">
                                    <dl class="mt-2 space-y-1">
                                        <template x-for="[k, v] in Object.entries(docs['{{ $type }}'].summary)" :key="k">
                                            <div class="flex justify-between gap-2 text-[12px]"><dt class="capitalize text-muted" x-text="k"></dt><dd class="mono truncate text-right text-ink" x-text="v"></dd></div>
                                        </template>
                                    </dl>
                                </template>
                                <template x-if="docs['{{ $type }}'].status === 'skipped'">
                                    <div class="mt-2 text-[12px] text-ink-2">{{ $type === 'photo' ? 'Photo saved.' : 'Saved — you will enter these details on the next step.' }}</div>
                                </template>
                                <template x-if="(docs['{{ $type }}'].issues || []).length">
                                    <div class="mt-2 rounded-md bg-warn-soft px-2 py-1 text-[11.5px] text-warn" x-text="'Note: ' + docs['{{ $type }}'].issues.join('; ')"></div>
                                </template>
                                <div class="mt-2 text-[11.5px] font-semibold text-brand">Tap to replace</div>
                            </div>
                        </template>

                        {{-- failed --}}
                        <template x-if="docs['{{ $type }}']?.status === 'failed'">
                            <div class="p-4">
                                <x-icon name="alert" :size="22" class="mx-auto text-bad" />
                                <div class="mt-2 text-[12.5px] font-semibold text-bad" x-text="docs['{{ $type }}'].error || 'Could not read this file.'"></div>
                                <div class="mt-1 text-[11.5px] text-muted">Tap to upload a clearer photo</div>
                            </div>
                        </template>
                    </label>
                </div>
            @endforeach
        </div>

        <div class="mt-8 flex flex-wrap items-center justify-between gap-3 border-t border-line pt-6">
            <p class="text-[12.5px] text-muted">
                <span x-show="busy()">Reading your documents… you can continue as soon as uploads finish.</span>
                <span x-show="!busy() && !complete()">Upload all required (*) documents to continue.</span>
                <span x-show="!busy() && complete()" class="text-ok">All set — {{ $aiEnabled ? 'check your auto-filled details next.' : 'complete your details next.' }}</span>
            </p>
            <a href="{{ route('apply.review', $app->tracking_no) }}" class="btn btn-primary h-11 px-6 text-[14px]"
               :class="(!complete() || busy()) ? 'pointer-events-none opacity-50' : ''" :aria-disabled="(!complete() || busy()).toString()">
                Review details <x-icon name="arrow-right" :size="16" />
            </a>
        </div>
    </div>
</x-admissions.public>
