@php
    $auto = $app->autofilled_fields ?? [];
    $a = fn (string $f) => in_array($f, $auto, true);
    $needsInter = $app->program?->basedOnInter();
    $boards = ['BISE Rawalpindi', 'FBISE Islamabad', 'BISE Lahore', 'BISE Gujranwala', 'BISE Sargodha', 'BISE Faisalabad', 'BISE Multan', 'BISE Peshawar', 'BISE Mirpur (AJK)', 'Aga Khan University EB', 'Other'];
@endphp
<x-admissions.public title="Review your details" :step="3">
    <div class="mb-6 flex flex-wrap items-end justify-between gap-4">
        <div>
            <div class="eyebrow">Step 3 of 3 · {{ $app->tracking_no }}</div>
            <h1 class="display mt-1 text-[32px] text-ink">Check and complete your details</h1>
            <p class="mt-1 max-w-2xl text-[13.5px] text-muted">
                @if ($auto)
                    Fields with a <span class="badge badge-brass badge-plain !h-[18px] !text-[10px]"><x-icon name="sparkles" :size="10" /> From documents</span> label were read from your uploads. Correct anything that is wrong — every field is required.
                @else
                    Fill in every field exactly as printed on your documents. The admission office compares them with your uploads.
                @endif
            </p>
        </div>
        <a href="{{ route('apply.documents', $app->tracking_no) }}" class="btn btn-ghost"><x-icon name="arrow-left" :size="15" /> Documents</a>
    </div>

    @if ($warnings->isNotEmpty())
        <div class="mb-6 rounded-2xl border border-warn/40 bg-warn-soft p-5">
            <div class="flex items-center gap-2 text-[14px] font-semibold text-warn"><x-icon name="alert" :size="17" /> Your documents don't fully agree</div>
            <ul class="mt-2 space-y-1 text-[13px] text-ink-2">
                @foreach ($warnings as $w)
                    <li>• <b>{{ $w->label }}</b> — {{ $w->note }}</li>
                @endforeach
            </ul>
            <p class="mt-2 text-[12px] text-muted">If the spelling really differs between documents, keep the spelling on your CNIC / B-Form and bring originals when called.</p>
        </div>
    @endif

    @if ($errors->any())
        <div class="mb-6 rounded-2xl border border-bad/30 bg-bad-soft p-4 text-[13px] text-bad">
            <b>{{ $errors->count() }} {{ \Illuminate\Support\Str::plural('field', $errors->count()) }} need attention.</b> They are highlighted below.
        </div>
    @endif

    <form method="POST" action="{{ route('apply.submit', $app->tracking_no) }}" class="space-y-5">
        @csrf
        <section class="panel p-6">
            <h2 class="panel-title mb-4 flex items-center gap-2"><span class="mono grid h-6 w-6 place-items-center rounded-md bg-sunken text-[11px]">1</span> Programme</h2>
            <div class="grid gap-4 sm:grid-cols-2">
                <x-ui.field name="program_id" label="Programme" type="select" required :value="$app->program_id" :options="$programs->pluck('name', 'id')->all()" :empty="false" />
                <x-ui.field name="second_program_id" label="Second preference" type="select" :value="$app->second_program_id" :options="$programs->pluck('name', 'id')->all()" empty="None" />
                <x-ui.field name="quota" label="Quota" type="select" required :value="$app->quota" :options="\App\Models\AdmissionApplication::QUOTAS" :empty="false" />
                <label class="flex items-center gap-2 self-end rounded-lg border border-line px-3 py-2.5 text-[13px] text-ink-2">
                    <input type="hidden" name="is_hafiz" value="0">
                    <input type="checkbox" name="is_hafiz" value="1" class="checkbox" @checked(old('is_hafiz', $app->is_hafiz))> I am a Hafiz-e-Quran
                </label>
            </div>
        </section>

        <section class="panel p-6">
            <h2 class="panel-title mb-4 flex items-center gap-2"><span class="mono grid h-6 w-6 place-items-center rounded-md bg-sunken text-[11px]">2</span> Personal details</h2>
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <x-ui.field name="name" label="Full name" required :value="$app->name" :autofilled="$a('name')" />
                <x-ui.field name="father_name" label="Father's name" required :value="$app->father_name" :autofilled="$a('father_name')" />
                <x-ui.field name="date_of_birth" label="Date of birth" type="date" required :value="$app->date_of_birth?->format('Y-m-d')" :autofilled="$a('date_of_birth')" />
                <x-ui.field name="cnic" label="CNIC / B-Form" required :value="$app->cnic" :autofilled="$a('cnic')" placeholder="37405-1234567-1" />
                <x-ui.field name="father_cnic" label="Father's CNIC" required :value="$app->father_cnic" :autofilled="$a('father_cnic')" placeholder="37405-1234567-1" />
                <x-ui.field name="gender" label="Gender" type="select" required :value="$app->gender" :options="['male' => 'Male', 'female' => 'Female']" :autofilled="$a('gender')" />
                <x-ui.field name="religion" label="Religion" type="select" required :value="$app->religion ?? 'Islam'" :options="['Islam' => 'Islam', 'Christianity' => 'Christianity', 'Hinduism' => 'Hinduism', 'Sikhism' => 'Sikhism', 'Other' => 'Other']" :empty="false" />
                <x-ui.field name="domicile" label="Domicile district" required :value="$app->domicile" :autofilled="$a('domicile')" placeholder="e.g. Rawalpindi" />
            </div>
        </section>

        <section class="panel p-6">
            <h2 class="panel-title mb-4 flex items-center gap-2"><span class="mono grid h-6 w-6 place-items-center rounded-md bg-sunken text-[11px]">3</span> Contact</h2>
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <x-ui.field name="phone" label="Your mobile" required :value="$app->phone" placeholder="0300-1234567" />
                <x-ui.field name="guardian_phone" label="Father / guardian mobile" required :value="$app->guardian_phone" placeholder="0300-1234567" />
                <x-ui.field name="email" label="Email" type="email" required :value="$app->email" />
                <x-ui.field name="address" label="Postal address" required :value="$app->address" class="sm:col-span-2" placeholder="House, street, area" />
                <x-ui.field name="city" label="City" required :value="$app->city ?? 'Rawalpindi'" />
            </div>
        </section>

        @foreach (array_filter(['matric' => 'Matric (SSC) result', 'inter' => $needsInter ? 'Intermediate (HSSC) result' : null]) as $lvl => $title)
            <section class="panel p-6">
                <h2 class="panel-title mb-4 flex items-center gap-2"><span class="mono grid h-6 w-6 place-items-center rounded-md bg-sunken text-[11px]">{{ $lvl === 'matric' ? 4 : 5 }}</span> {{ $title }}</h2>
                @php $boardVal = $app->{$lvl.'_board'}; $opts = array_combine($boards, $boards); if ($boardVal && ! isset($opts[$boardVal])) $opts = [$boardVal => $boardVal] + $opts; @endphp
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <x-ui.field :name="$lvl.'_board'" label="Board" type="select" required :value="$boardVal" :options="$opts" :autofilled="$a($lvl.'_board')" />
                    <x-ui.field :name="$lvl.'_roll_no'" label="Roll number" required :value="$app->{$lvl.'_roll_no'}" :autofilled="$a($lvl.'_roll_no')" />
                    <x-ui.field :name="$lvl.'_year'" label="Passing year" type="number" required :value="$app->{$lvl.'_year'}" :autofilled="$a($lvl.'_year')" />
                    <x-ui.field :name="$lvl.'_group'" label="Group" required :value="$app->{$lvl.'_group'}" :autofilled="$a($lvl.'_group')" :placeholder="$lvl === 'matric' ? 'Science' : 'Pre-Engineering'" />
                    <x-ui.field :name="$lvl.'_obtained'" label="Marks obtained" type="number" required :value="$app->{$lvl.'_obtained'}" :autofilled="$a($lvl.'_obtained')" />
                    <x-ui.field :name="$lvl.'_total'" label="Total marks" type="number" required :value="$app->{$lvl.'_total'}" :autofilled="$a($lvl.'_total')" />
                </div>
            </section>
        @endforeach

        <section class="panel p-6">
            <label class="flex items-start gap-3 text-[13.5px] leading-relaxed text-ink-2">
                <input type="checkbox" name="declaration" value="1" class="checkbox mt-1" @checked(old('declaration')) required>
                <span>I declare that the information and documents provided are correct. I understand that admission will be cancelled at any stage if anything is found false, and I will abide by the rules of the college.</span>
            </label>
            @error('declaration')<div class="field-error ml-7">{{ $message }}</div>@enderror
        </section>

        <div class="flex flex-wrap items-center justify-between gap-3">
            <p class="text-[12.5px] text-muted">Deadline: {{ $cycle->closes_at->format('l, j F Y · g:i a') }}</p>
            <button class="btn btn-brass h-12 px-7 text-[14.5px]">Submit application <x-icon name="arrow-right" :size="16" /></button>
        </div>
    </form>
</x-admissions.public>
