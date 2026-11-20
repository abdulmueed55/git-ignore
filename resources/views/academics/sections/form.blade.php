@php $editing = $section->exists; @endphp
<x-layouts.app :title="$editing ? 'Edit section' : 'New section'">
    <x-ui.page-header eyebrow="Sections" :title="$editing ? 'Edit '.$section->name : 'New section'" description="Every field is required — the incharge receives the section's attendance alerts.">
        <x-slot:actions><a href="{{ $editing ? route('academics.sections.show', $section) : route('academics.sections.index') }}" class="btn btn-ghost">Cancel</a></x-slot:actions>
    </x-ui.page-header>
    <form method="POST" action="{{ $editing ? route('academics.sections.update', $section) : route('academics.sections.store') }}" class="panel max-w-3xl">
        @csrf @if ($editing) @method('PUT') @endif
        <div class="grid gap-4 p-5 sm:grid-cols-2">
            <x-ui.field name="program_id" type="select" label="Program" :value="$section->program_id ?? request('program')" :options="$programs" required />
            <x-ui.field name="academic_session_id" type="select" label="Session" :value="$section->academic_session_id" :options="$sessions" required />
            <x-ui.field name="name" label="Section name" :value="$section->name" required placeholder="Part-I · C" />
            <x-ui.field name="year" type="number" label="Year of study" :value="$section->year" required min="1" max="5" />
            <x-ui.field name="room" label="Home room" :value="$section->room" required placeholder="B-103" />
            <x-ui.field name="capacity" type="number" label="Capacity" :value="$section->capacity" required min="5" />
            <x-ui.field name="incharge_id" type="select" label="Section incharge" :value="$section->incharge_id" :options="$teachers" required class="sm:col-span-2" />
        </div>
        <div class="flex justify-end border-t border-line px-5 py-4">
            <button class="btn btn-primary"><x-icon name="check" :size="16" /> {{ $editing ? 'Save section' : 'Create section' }}</button>
        </div>
    </form>
</x-layouts.app>
