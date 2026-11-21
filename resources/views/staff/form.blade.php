@php $editing = $user->exists; @endphp
<x-layouts.app :title="$editing ? 'Edit '.$user->name : 'Add staff member'">
    <x-ui.page-header eyebrow="Staff & roles" :title="$editing ? $user->name : 'Add a staff member'"
        :description="$editing ? 'Update role, department and contact details.' : 'A temporary password is generated and shown once after saving.'">
        <x-slot:actions><a href="{{ route('staff.index') }}" class="btn btn-ghost">Cancel</a></x-slot:actions>
    </x-ui.page-header>

    <form method="POST" action="{{ $editing ? route('staff.update', $user) : route('staff.store') }}" class="grid gap-6 xl:grid-cols-[1fr_340px]" x-data="{ role: @js((string) old('role', $user->role)) }">
        @csrf @if ($editing) @method('PUT') @endif
        <section class="panel">
            <div class="panel-head"><div class="panel-title">Account</div></div>
            <div class="grid gap-4 p-5 sm:grid-cols-2">
                <x-ui.field name="name" label="Full name with title" :value="$user->name" required placeholder="Mr. Imran Ahmed Qureshi" class="sm:col-span-2" />
                <x-ui.field name="email" type="email" label="College email" :value="$user->email" required placeholder="name@fglacollege.edu.pk" />
                <x-ui.field name="phone" label="Phone" :value="$user->phone" required placeholder="0300-1234567" />
                <x-ui.field name="employee_no" label="Employee no" :value="$user->employee_no" required />
                <x-ui.field name="designation" type="select" label="Designation" :value="$user->designation" :options="$designations + ($user->designation ? [$user->designation => $user->designation] : [])" required />
                <div class="sm:col-span-2">
                    <label class="field-label" for="f_department_id">Department <span class="req" x-show="role === 'teacher'">*</span></label>
                    <select id="f_department_id" name="department_id" class="input" :required="role === 'teacher'" @if($errors->has('department_id')) aria-invalid="true" @endif>
                        <option value="">No department (administrative staff)</option>
                        @foreach ($departments as $id => $n)<option value="{{ $id }}" @selected(old('department_id', $user->department_id) == $id)>{{ $n }}</option>@endforeach
                    </select>
                    @error('department_id')<div class="field-error">{{ $message }}</div>@enderror
                </div>
            </div>
        </section>
        <aside class="space-y-6">
            <section class="panel">
                <div class="panel-head"><div class="panel-title">Role & access</div><x-icon name="shield" :size="16" class="text-brass" /></div>
                <div class="space-y-2 p-4">
                    @php
                        $desc = [
                            'admin' => 'Everything, including administrator accounts.',
                            'principal' => 'All modules, staff management and the website.',
                            'admission_officer' => 'Admissions desk, AI verification, merit lists, students.',
                            'teacher' => 'Own timetable, attendance and students they teach.',
                            'editor' => 'Website studio only — news, notices, pages, gallery.',
                        ];
                    @endphp
                    @foreach ($roles as $key => $label)
                        <label class="flex cursor-pointer items-start gap-3 rounded-lg border p-3 transition" :class="role === '{{ $key }}' ? 'border-brand bg-brand-soft/50' : 'border-line hover:border-line-strong'">
                            <input type="radio" name="role" value="{{ $key }}" x-model="role" class="mt-0.5 accent-[var(--brand)]">
                            <span><span class="block text-[13px] font-semibold text-ink">{{ $label }}</span><span class="block text-[12px] text-muted">{{ $desc[$key] }}</span></span>
                        </label>
                    @endforeach
                    @error('role')<div class="field-error">{{ $message }}</div>@enderror
                </div>
            </section>
            <button class="btn btn-primary h-11 w-full"><x-icon name="check" :size="16" /> {{ $editing ? 'Save changes' : 'Create account' }}</button>
        </aside>
    </form>
</x-layouts.app>
