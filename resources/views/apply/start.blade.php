<x-admissions.public title="Apply online" :step="$open ? 1 : null">
    @if (! $cycle || ! $open)
        <div class="mx-auto max-w-xl py-10 text-center">
            <div class="mx-auto grid h-14 w-14 place-items-center rounded-2xl border border-line bg-surface text-brass"><x-icon name="calendar" :size="24" /></div>
            <h1 class="display mt-5 text-[34px] text-ink">Admissions are closed right now</h1>
            <p class="mt-3 text-[14.5px] leading-relaxed text-muted">
                @if ($cycle && now()->lt($cycle->opens_at))
                    <b class="text-ink">{{ $cycle->title }}</b> opens on {{ $cycle->opens_at->format('l, j F Y') }}.
                @else
                    The next intake will be announced on the college website and notice board.
                @endif
                Already applied? You can still check your status and merit lists.
            </p>
            <div class="mt-6 flex justify-center gap-2">
                <a href="{{ route('apply.status') }}" class="btn btn-primary">Check application status</a>
                <a href="{{ url('/') }}" class="btn btn-ghost">College website</a>
            </div>
        </div>
    @else
        <div class="grid gap-8 lg:grid-cols-[1fr_440px]">
            {{-- Information --}}
            <section>
                <div class="eyebrow flex items-center gap-2"><span class="h-px w-5 bg-brass"></span>{{ $cycle->academicSession?->name }} · applications open</div>
                <h1 class="display mt-3 text-[38px] leading-[1.08] text-ink sm:text-[46px]">{{ $cycle->title }}</h1>
                <div class="mt-5 flex flex-wrap gap-3">
                    <div class="rounded-xl border border-line bg-surface px-4 py-3">
                        <div class="numeral text-[30px] text-ink">{{ $cycle->daysLeft() }}</div>
                        <div class="eyebrow mt-1 !text-[9.5px]">days to apply</div>
                    </div>
                    <div class="rounded-xl border border-line bg-surface px-4 py-3">
                        <div class="numeral text-[30px] text-ink">{{ $cycle->closes_at->format('j M') }}</div>
                        <div class="eyebrow mt-1 !text-[9.5px]">last date · {{ $cycle->closes_at->format('g:i a') }}</div>
                    </div>
                    <div class="rounded-xl border border-line bg-surface px-4 py-3">
                        <div class="numeral text-[30px] text-ink">Rs {{ number_format($cycle->application_fee) }}</div>
                        <div class="eyebrow mt-1 !text-[9.5px]">processing fee</div>
                    </div>
                </div>

                <div class="prose-ledger mt-6 whitespace-pre-line">{{ $cycle->instructions }}</div>

                @foreach ($programs->groupBy(fn ($p) => $p->level === 'intermediate' ? 'Intermediate · admission in 1st Year (HSSC Part-I)' : 'BS · admission in 1st Semester')->sortKeysDesc() as $group => $items)
                    <h2 class="eyebrow mt-8 mb-3">{{ $group }}</h2>
                    <div class="grid gap-3 sm:grid-cols-2">
                        @foreach ($items as $p)
                            <div class="panel p-4">
                                <div class="flex items-center justify-between">
                                    <span class="mono text-[11.5px] font-semibold text-brand">{{ $p->code }}</span>
                                    <span class="text-[11.5px] text-muted">{{ $p->seats }} seats</span>
                                </div>
                                <div class="mt-1 font-semibold text-ink">{{ $p->name }}</div>
                                <div class="mt-1 text-[12.5px] text-muted">{{ $p->eligibility }}</div>
                                <div class="mt-2 text-[12px] text-ink-2">{{ $p->admissionClass() }} · {{ $p->duration_years }} years · Rs {{ number_format($p->annual_fee) }} / year</div>
                            </div>
                        @endforeach
                    </div>
                @endforeach

                <div class="panel mt-6 p-5">
                    <div class="flex items-center gap-2 text-[13.5px] font-semibold text-ink"><x-icon name="sparkles" :size="16" class="text-brass" /> Keep these ready — we read them for you</div>
                    <p class="mt-1 text-[12.5px] text-muted">Take clear photos (or PDFs). Your details are filled in automatically from the documents, and you only check them.</p>
                    <ul class="mt-3 grid gap-2 text-[13px] sm:grid-cols-2">
                        @foreach (\App\Models\AdmissionApplication::DOCUMENT_TYPES as $type => $label)
                            <li class="flex items-center gap-2"><x-icon name="check" :size="14" class="text-ok" /> {{ $label }}
                                @if ($type === 'inter_dmc')<span class="text-[11px] text-muted">(BS only)</span>@endif
                                @if ($type === 'character_certificate')<span class="text-[11px] text-muted">(optional)</span>@endif
                            </li>
                        @endforeach
                    </ul>
                </div>
            </section>

            {{-- Step 1 form --}}
            <section>
                <form method="POST" action="{{ route('apply.store') }}" class="panel sticky top-6 space-y-4 p-6 shadow-sm">
                    @csrf
                    <div>
                        <div class="eyebrow">Step 1 of 3</div>
                        <h2 class="display mt-1 text-[24px] text-ink">Start your application</h2>
                        <p class="mt-1 text-[12.5px] text-muted">Takes about 5 minutes. You'll get a tracking number and PIN to come back later.</p>
                    </div>
                    <div class="rounded-md border border-line bg-sunken/60 px-3 py-2.5 text-[12.5px] text-ink-2">
                        Admission is offered only in <b class="text-ink">1st Year (HSSC Part-I)</b> for intermediate programmes and the <b class="text-ink">1st semester</b> for BS.
                    </div>
                    <x-admissions.program-select name="program_id" label="Programme" :programs="$programs" required />
                    <x-admissions.program-select name="second_program_id" label="Second preference" :programs="$programs" empty="None" hint="Considered if the first choice is full." />
                    <x-ui.field name="name" label="Full name (as on CNIC / B-Form)" required placeholder="e.g. Muhammad Ahmed" autocomplete="name" />
                    <x-ui.field name="cnic" label="CNIC / B-Form number" required placeholder="37405-1234567-1" inputmode="numeric" class="" />
                    <div class="grid gap-4 sm:grid-cols-2">
                        <x-ui.field name="phone" label="Mobile number" required placeholder="0300-1234567" inputmode="tel" />
                        <x-ui.field name="email" label="Email" type="email" required placeholder="you@gmail.com" />
                    </div>
                    <x-ui.field name="quota" label="Applying under" type="select" required :options="\App\Models\AdmissionApplication::QUOTAS" value="open" :empty="false" />
                    <label class="flex items-center gap-2 rounded-lg border border-line px-3 py-2.5 text-[13px] text-ink-2">
                        <input type="checkbox" name="is_hafiz" value="1" class="checkbox" @checked(old('is_hafiz'))>
                        I am a Hafiz-e-Quran <span class="text-[11.5px] text-muted">(+20 marks in merit, verified at the college)</span>
                    </label>
                    <button class="btn btn-primary h-11 w-full text-[14px]">Continue to documents <x-icon name="arrow-right" :size="16" /></button>
                    <p class="text-center text-[12px] text-muted">Already started? <a href="{{ route('apply.status') }}" class="font-semibold text-brand hover:underline">Continue with your PIN</a></p>
                </form>
            </section>
        </div>
    @endif
</x-admissions.public>
