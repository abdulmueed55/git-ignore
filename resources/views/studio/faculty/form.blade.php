<x-layouts.app :title="$profile->exists ? 'Edit profile' : 'Add profile'" area="studio">
    <x-ui.page-header eyebrow="Faculty directory" :title="$profile->exists ? $profile->name : 'Add a faculty profile'" description="Shown on the website's faculty page, grouped by department." />
    <form method="POST" enctype="multipart/form-data" action="{{ $profile->exists ? route('studio.faculty.update', $profile) : route('studio.faculty.store') }}"
          class="grid max-w-5xl gap-6 lg:grid-cols-[220px_1fr]" x-data="{ photo: @js($profile->photoUrl()) }">
        @csrf @if ($profile->exists) @method('PUT') @endif
        <div class="panel h-max p-5 text-center">
            <label class="group relative mx-auto block h-36 w-36 cursor-pointer overflow-hidden rounded-full border border-dashed border-line-strong bg-sunken">
                <template x-if="photo"><img :src="photo" class="h-full w-full object-cover" alt=""></template>
                <span x-show="!photo" class="absolute inset-0 grid place-items-center text-muted"><x-icon name="image" :size="26" /></span>
                <input type="file" name="photo" accept="image/jpeg,image/png,image/webp" class="sr-only" @change="const f=$event.target.files[0]; if(f) photo=URL.createObjectURL(f)">
            </label>
            <div class="mt-3 text-[12px] text-muted">Square photo, plain background. Optional — initials are shown otherwise.</div>
            @error('photo')<div class="field-error">{{ $message }}</div>@enderror
        </div>
        <section class="panel space-y-4 p-5 sm:p-6">
            <div class="grid gap-4 sm:grid-cols-2">
                <x-ui.field name="name" label="Full name" required :value="$profile->name" />
                <x-ui.field name="designation" label="Designation" type="select" required :value="$profile->designation"
                    :options="array_combine($d = ['Principal', 'Vice Principal', 'Professor', 'Associate Professor', 'Assistant Professor', 'Lecturer', 'Visiting Lecturer', 'Lab Demonstrator'], $d)" />
                <x-ui.field name="department_id" label="Department" type="select" required :options="$departments" :value="$profile->department_id" />
                <x-ui.field name="email" label="Public email" type="email" :value="$profile->email" hint="Optional" />
                <x-ui.field name="qualification" label="Highest qualification" required :value="$profile->qualification" placeholder="e.g. M.Phil Physics (QAU)" />
                <x-ui.field name="specialization" label="Specialization" required :value="$profile->specialization" placeholder="e.g. Solid-state physics" />
            </div>
            <x-ui.field name="bio" label="Short bio" type="textarea" rows="4" :value="$profile->bio" hint="Optional · up to 1,200 characters" />
            <div class="grid gap-4 sm:grid-cols-2">
                <x-ui.field name="sort_order" label="Order in directory" type="number" min="0" required :value="$profile->sort_order" hint="Lower numbers appear first" />
                <label class="mt-7 flex items-center gap-2 text-[13px] text-ink-2"><input type="checkbox" name="is_published" value="1" class="checkbox" @checked(old('is_published', $profile->is_published))> Show on the website</label>
            </div>
            <div class="flex gap-2 border-t border-line pt-4">
                <button class="btn btn-primary">Save profile</button>
                <a href="{{ route('studio.faculty.index') }}" class="btn btn-ghost">Cancel</a>
                @if ($profile->exists)<button form="del-fac" class="btn btn-danger ml-auto" onclick="return confirm('Remove this profile from the directory?')">Remove</button>@endif
            </div>
        </section>
    </form>
    @if ($profile->exists)<form id="del-fac" method="POST" action="{{ route('studio.faculty.destroy', $profile) }}" class="hidden">@csrf @method('DELETE')</form>@endif
</x-layouts.app>
