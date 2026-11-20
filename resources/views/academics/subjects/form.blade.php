@php $editing = $subject->exists; @endphp
<x-layouts.app :title="$editing ? 'Edit subject' : 'New subject'">
    <x-ui.page-header eyebrow="Subjects" :title="$editing ? 'Edit '.$subject->code : 'New subject'" description="Subjects become courses once assigned to a section and teacher in the timetable.">
        <x-slot:actions><a href="{{ route('academics.subjects.index') }}" class="btn btn-ghost">Cancel</a></x-slot:actions>
    </x-ui.page-header>
    <form method="POST" action="{{ $editing ? route('academics.subjects.update', $subject) : route('academics.subjects.store') }}" class="panel max-w-2xl">
        @csrf @if ($editing) @method('PUT') @endif
        <div class="grid gap-4 p-5 sm:grid-cols-2">
            <x-ui.field name="name" label="Subject name" :value="$subject->name" required class="sm:col-span-2" />
            <x-ui.field name="code" label="Code" :value="$subject->code" required placeholder="CS-101" />
            <x-ui.field name="department_id" type="select" label="Department" :value="$subject->department_id" :options="$departments" required />
            <x-ui.field name="credit_hours" type="number" label="Credit hours" :value="$subject->credit_hours" required min="1" max="6" />
            <label class="flex items-center gap-2 self-end pb-2 text-[13px] text-ink-2">
                <input type="hidden" name="has_practical" value="0"><input type="checkbox" name="has_practical" value="1" class="checkbox" @checked(old('has_practical', $subject->has_practical))> Has a lab / practical component
            </label>
        </div>
        <div class="flex justify-end border-t border-line px-5 py-4"><button class="btn btn-primary"><x-icon name="check" :size="16" /> Save subject</button></div>
    </form>
</x-layouts.app>
