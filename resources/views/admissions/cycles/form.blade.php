<x-layouts.app :title="$cycle->exists ? 'Edit cycle' : 'New cycle'">
    <x-ui.page-header eyebrow="Admission cycles" :title="$cycle->exists ? $cycle->title : 'New admission cycle'"
        description="Applicants see the title, deadline, fee and instructions on the online form." />

    <form method="POST" action="{{ $cycle->exists ? route('admissions.cycles.update', $cycle) : route('admissions.cycles.store') }}" class="grid grid-cols-1 gap-4 lg:grid-cols-[1fr_320px]">
        @csrf
        @if ($cycle->exists) @method('PUT') @endif
        <div class="panel space-y-4 p-5">
            <x-ui.field name="title" label="Title" required :value="$cycle->title" placeholder="e.g. Fall 2027 Intake — Intermediate & BS" />
            <div class="grid gap-4 sm:grid-cols-2">
                <x-ui.field name="academic_session_id" label="Academic session" type="select" required :value="$cycle->academic_session_id" :options="$sessions->all()" />
                <x-ui.field name="application_fee" label="Application fee (Rs)" type="number" required :value="$cycle->application_fee ?? 0" min="0" />
                <x-ui.field name="opens_at" label="Opens" type="datetime-local" required :value="$cycle->opens_at?->format('Y-m-d\TH:i')" />
                <x-ui.field name="closes_at" label="Closes" type="datetime-local" required :value="$cycle->closes_at?->format('Y-m-d\TH:i')" />
            </div>
            <x-ui.field name="instructions" label="Instructions for applicants" type="textarea" rows="6" required :value="$cycle->instructions"
                hint="Eligibility, documents needed, fee deposit method. Minimum 20 characters." />
        </div>
        <div class="space-y-4">
            <div class="panel space-y-3 p-5">
                <x-ui.field name="status" label="Status" type="select" required :empty="false" :value="$cycle->status"
                    :options="['draft' => 'Draft — hidden', 'open' => 'Open — accepting applications', 'closed' => 'Closed', 'archived' => 'Archived']" />
                <div>
                    <div class="field-label">Programmes offered <span class="req">*</span></div>
                    @php $selected = collect(old('program_ids', $cycle->program_ids ?? []))->map(fn ($v) => (int) $v)->all(); @endphp
                    <div class="max-h-[260px] space-y-1 overflow-y-auto rounded-lg border border-line p-2">
                        @foreach ($programs as $p)
                            <label class="flex items-center gap-2 rounded-md px-2 py-1.5 text-[13px] hover:bg-sunken">
                                <input type="checkbox" name="program_ids[]" value="{{ $p->id }}" class="checkbox" @checked(in_array($p->id, $selected))>
                                <span class="mono w-14 text-[11.5px] text-brass">{{ $p->code }}</span><span class="truncate">{{ $p->name }}</span>
                            </label>
                        @endforeach
                    </div>
                    @error('program_ids')<div class="field-error">{{ $message }}</div>@enderror
                </div>
            </div>
            <div class="flex gap-2">
                <button class="btn btn-primary flex-1">{{ $cycle->exists ? 'Save cycle' : 'Create cycle' }}</button>
                <a href="{{ route('admissions.cycles.index') }}" class="btn btn-ghost">Cancel</a>
            </div>
        </div>
    </form>
</x-layouts.app>
