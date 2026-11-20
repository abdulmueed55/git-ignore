@php $editing = $student->exists; @endphp
<x-layouts.app :title="$editing ? 'Edit '.$student->name : 'Enrol student'">
    <x-ui.page-header eyebrow="Students" :title="$editing ? 'Edit student record' : 'Enrol a student'"
        description="All fields are required. CNIC/B-Form as 37405-1234567-1 and phones as 0300-1234567.">
        <x-slot:actions>
            <a href="{{ $editing ? route('students.show', $student) : route('students.index') }}" class="btn btn-ghost">Cancel</a>
        </x-slot:actions>
    </x-ui.page-header>

    @if ($errors->any())
        <div class="mb-5 flex items-start gap-3 rounded-xl border border-bad/30 bg-bad-soft px-4 py-3 text-[13px] text-bad">
            <x-icon name="alert" :size="16" class="mt-0.5" />
            <div><strong>{{ $errors->count() }} {{ Str::plural('field', $errors->count()) }} need attention.</strong> Fix the highlighted fields below and save again.</div>
        </div>
    @endif

    <form method="POST" action="{{ $editing ? route('students.update', $student) : route('students.store') }}"
          x-data="{ program: '{{ old('program_id', $student->program_id) }}' }" class="grid gap-6 xl:grid-cols-[1fr_340px]">
        @csrf
        @if ($editing) @method('PUT') @endif

        <div class="space-y-6">
            <section class="panel">
                <div class="panel-head"><div class="panel-title">Identity</div><span class="eyebrow">Step 1 of 3</span></div>
                <div class="grid gap-4 p-5 sm:grid-cols-2">
                    <x-ui.field name="name" label="Full name" :value="$student->name" required placeholder="As on Matric certificate" />
                    <x-ui.field name="father_name" label="Father's name" :value="$student->father_name" required />
                    <x-ui.field name="cnic" label="CNIC / B-Form" :value="$student->cnic" required placeholder="37405-1234567-1" class="mono" inputmode="numeric" />
                    <x-ui.field name="father_cnic" label="Father's CNIC" :value="$student->father_cnic" required placeholder="37405-7654321-3" inputmode="numeric" />
                    <x-ui.field name="date_of_birth" type="date" label="Date of birth" :value="$student->date_of_birth?->format('Y-m-d')" required />
                    <div class="grid grid-cols-2 gap-4">
                        <x-ui.field name="gender" type="select" label="Gender" :value="$student->gender" required :options="['male' => 'Male', 'female' => 'Female']" :empty="false" />
                        <x-ui.field name="blood_group" type="select" label="Blood group" :value="$student->blood_group" required :options="$bloodGroups" />
                    </div>
                </div>
            </section>

            <section class="panel">
                <div class="panel-head"><div class="panel-title">Contact & residence</div><span class="eyebrow">Step 2 of 3</span></div>
                <div class="grid gap-4 p-5 sm:grid-cols-2">
                    <x-ui.field name="phone" label="Student phone" :value="$student->phone" required placeholder="0300-1234567" inputmode="tel" />
                    <x-ui.field name="guardian_phone" label="Guardian phone" :value="$student->guardian_phone" required placeholder="0300-1234567" hint="Absence alerts are sent to this number." inputmode="tel" />
                    <x-ui.field name="email" type="email" label="Email" :value="$student->email" required class="sm:col-span-2" />
                    <x-ui.field name="address" label="Postal address" :value="$student->address" required class="sm:col-span-2" placeholder="House, street, area" />
                    <x-ui.field name="city" label="City" :value="$student->city" required />
                    <x-ui.field name="domicile" label="Domicile district" :value="$student->domicile" required />
                </div>
            </section>
        </div>

        <aside class="space-y-6">
            <section class="panel">
                <div class="panel-head"><div class="panel-title">Placement</div><span class="eyebrow">Step 3 of 3</span></div>
                <div class="space-y-4 p-5">
                    <div>
                        <label for="f_program_id" class="field-label">Program <span class="req">*</span></label>
                        <select id="f_program_id" name="program_id" class="input" x-model="program" required @if($errors->has('program_id')) aria-invalid="true" @endif>
                            <option value="">Select program…</option>
                            @foreach ($programs as $p)
                                <option value="{{ $p->id }}">{{ $p->name }}</option>
                            @endforeach
                        </select>
                        @error('program_id')<div class="field-error">{{ $message }}</div>@enderror
                    </div>
                    <div>
                        <label for="f_section_id" class="field-label">Section <span class="req">*</span></label>
                        <select id="f_section_id" name="section_id" class="input" required @if($errors->has('section_id')) aria-invalid="true" @endif>
                            <option value="">Select section…</option>
                            @foreach ($sections as $s)
                                <option value="{{ $s->id }}" data-program="{{ $s->program_id }}" x-show="program == '{{ $s->program_id }}'" :disabled="program != '{{ $s->program_id }}'"
                                    @selected(old('section_id', $student->section_id) == $s->id)>{{ $s->name }} · {{ $s->academicSession?->name }}</option>
                            @endforeach
                        </select>
                        @error('section_id')<div class="field-error">{{ $message }}</div>@else<div class="field-hint">Only sections of the selected program are listed.</div>@enderror
                    </div>
                    <x-ui.field name="academic_session_id" type="select" label="Session" :value="$student->academic_session_id" required :options="$sessions" hint="Matched to the section’s session automatically." />
                    <x-ui.field name="roll_no" label="College roll no" :value="$student->roll_no" required placeholder="FSC-PM-26-041" class="mono" />
                    <x-ui.field name="registration_no" label="Board registration no" :value="$student->registration_no" required placeholder="BISE-RWP-26123456" />
                    <x-ui.field name="status" type="select" label="Status" :value="$student->status" required :options="$statuses" :empty="false" />
                </div>
            </section>
            <button class="btn btn-primary h-11 w-full"><x-icon name="check" :size="16" /> {{ $editing ? 'Save changes' : 'Enrol student' }}</button>
        </aside>
    </form>
</x-layouts.app>
