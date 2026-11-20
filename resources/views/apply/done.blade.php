<x-admissions.public title="Application submitted" :step="4">
    <div class="mx-auto max-w-2xl">
        <div class="panel relative overflow-hidden p-8 text-center sm:p-10">
            <div class="hairline-grid pointer-events-none absolute inset-0 opacity-40 [mask-image:radial-gradient(ellipse_at_top,black,transparent_65%)]"></div>
            <div class="relative">
                <div class="mx-auto grid h-14 w-14 place-items-center rounded-full bg-ok text-white"><x-icon name="check" :size="26" /></div>
                <h1 class="display mt-5 text-[34px] text-ink">Application submitted</h1>
                <p class="mt-2 text-[14px] text-muted">{{ $app->name }} · {{ $app->program?->name }} · {{ $app->submitted_at?->format('j M Y, g:i a') }}</p>

                <div class="mt-7 grid gap-3 sm:grid-cols-2">
                    <div class="rounded-xl border border-line bg-paper p-4">
                        <div class="eyebrow">Tracking number</div>
                        <div class="mono mt-1 text-[22px] font-semibold text-ink">{{ $app->tracking_no }}</div>
                    </div>
                    <div class="rounded-xl border border-line bg-paper p-4">
                        <div class="eyebrow">PIN</div>
                        <div class="mono mt-1 text-[22px] font-semibold tracking-[0.2em] text-ink">{{ $pin }}</div>
                    </div>
                </div>
                <p class="mt-3 text-[12.5px] text-muted">Write these down or take a screenshot — you need both to check your status and merit lists.</p>
            </div>
        </div>

        <div class="panel mt-4 p-6">
            <div class="eyebrow mb-4">What happens next</div>
            <ol class="space-y-4">
                @foreach ([
                    ['Document check', 'The admission office compares your documents with the form. If anything differs, you will see it on your status page.'],
                    ['Merit lists', 'Lists are published on '.$app->cycle->closes_at->addDays(5)->format('j M').' onwards on the status page and the college notice board.'],
                    ['Fee & originals', 'If selected, deposit the fee before the deadline and bring original documents to the college.'],
                ] as $i => [$h, $t])
                    <li class="flex gap-4">
                        <span class="numeral mt-0.5 text-[22px] text-brass">{{ $i + 1 }}</span>
                        <div><div class="text-[13.5px] font-semibold text-ink">{{ $h }}</div><div class="text-[13px] text-muted">{{ $t }}</div></div>
                    </li>
                @endforeach
            </ol>
        </div>

        <div class="mt-6 flex flex-wrap justify-center gap-2 print:hidden">
            <a href="{{ route('apply.status', ['tracking_no' => $app->tracking_no]) }}" class="btn btn-primary">View status</a>
            <button onclick="window.print()" class="btn btn-ghost"><x-icon name="printer" :size="15" /> Print acknowledgement</button>
        </div>
    </div>
</x-admissions.public>
