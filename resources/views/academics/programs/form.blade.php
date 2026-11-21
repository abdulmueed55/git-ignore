@php $editing = $program->exists; $w = $program->weights(); @endphp
<x-layouts.app :title="$editing ? 'Edit '.$program->code : 'New program'">
    <x-ui.page-header eyebrow="Programs" :title="$editing ? 'Edit '.$program->name : 'New program'" description="Seats, eligibility and the merit formula used to rank applicants.">
        <x-slot:actions><a href="{{ $editing ? route('academics.programs.show', $program) : route('academics.programs.index') }}" class="btn btn-ghost">Cancel</a></x-slot:actions>
    </x-ui.page-header>

    <form method="POST" action="{{ $editing ? route('academics.programs.update', $program) : route('academics.programs.store') }}" class="grid gap-6 xl:grid-cols-[1fr_360px]"
          x-data="{ level: @js((string) old('level', $program->level)), academic: @js((int) old('weights.academic', $w['academic'])), matric: @js((int) old('weights.matric', $w['matric'] ?? 0)) }">
        @csrf @if ($editing) @method('PUT') @endif
        <section class="panel">
            <div class="panel-head"><div class="panel-title">Program details</div></div>
            <div class="grid gap-4 p-5 sm:grid-cols-2">
                <x-ui.field name="name" label="Program name" :value="$program->name" required class="sm:col-span-2" placeholder="F.Sc. Pre-Medical" />
                <x-ui.field name="code" label="Code" :value="$program->code" required placeholder="FSC-PM" />
                <x-ui.field name="department_id" type="select" label="Department" :value="$program->department_id" :options="$departments" required />
                <div>
                    <label class="field-label" for="f_level">Level <span class="req">*</span></label>
                    <select id="f_level" name="level" class="input" x-model="level" required>
                        @foreach (\App\Models\Program::LEVELS as $k => $v)<option value="{{ $k }}">{{ $v }}</option>@endforeach
                    </select>
                    @error('level')<div class="field-error">{{ $message }}</div>@enderror
                </div>
                <x-ui.field name="duration_years" type="number" label="Duration (years)" :value="$program->duration_years" required min="1" max="5" />
                <x-ui.field name="seats" type="number" label="Seats per intake" :value="$program->seats" required min="1" />
                <x-ui.field name="shift" type="select" label="Shift" :value="$program->shift" :options="['morning' => 'Morning', 'evening' => 'Evening']" required :empty="false" />
                <x-ui.field name="min_percentage" type="number" step="0.5" label="Minimum marks (%)" :value="$program->min_percentage" required />
                <x-ui.field name="annual_fee" type="number" label="Annual fee (Rs)" :value="$program->annual_fee" required min="0" />
                <x-ui.field name="eligibility" type="textarea" label="Eligibility" :value="$program->eligibility" required class="sm:col-span-2" rows="2" placeholder="Matric (Science) with Biology, minimum 60% marks" />
                <label class="flex items-center gap-2 text-[13px] text-ink-2 sm:col-span-2">
                    <input type="hidden" name="is_active" value="0"><input type="checkbox" name="is_active" value="1" class="checkbox" @checked(old('is_active', $program->is_active))> Accepting admissions (active)
                </label>
            </div>
        </section>
        <aside class="space-y-6">
            <section class="panel">
                <div class="panel-head"><div class="panel-title">Merit formula</div><x-icon name="trophy" :size="16" class="text-brass" /></div>
                <div class="space-y-4 p-5">
                    <p class="text-[12.5px] text-muted" x-show="level === 'intermediate'">Intermediate programs rank applicants on Matric marks percentage.</p>
                    <div x-show="level !== 'intermediate'" class="space-y-4">
                        <x-ui.field name="weights[academic]" type="number" step="0.05" min="0" max="1" label="Intermediate weight" :value="$w['academic']" x-model.number="academic" required />
                        <x-ui.field name="weights[matric]" type="number" step="0.05" min="0" max="1" label="Matric weight" :value="$w['matric'] ?? 0" x-model.number="matric" required />
                        <div class="flex items-center justify-between rounded-lg bg-sunken px-3 py-2 text-[12px]">
                            <span class="text-muted">Weights total</span>
                            <span class="mono font-semibold" :class="Math.abs(academic + matric - 1) < 0.001 ? 'text-ok' : 'text-bad'" x-text="(academic + matric).toFixed(2)"></span>
                        </div>
                    </div>
                    <template x-if="level === 'intermediate'"><div><input type="hidden" name="weights[academic]" value="1"><input type="hidden" name="weights[matric]" value="0"></div></template>
                    <x-ui.field name="weights[hafiz_bonus]" type="number" min="0" max="50" label="Hafiz-e-Quran bonus marks" :value="$w['hafiz_bonus']" required hint="Added to the merit score of verified Huffaz." />
                </div>
            </section>
            <button class="btn btn-primary h-11 w-full"><x-icon name="check" :size="16" /> {{ $editing ? 'Save program' : 'Create program' }}</button>
        </aside>
    </form>
</x-layouts.app>
