{{--
  Programme picker grouped by the class admission is offered in:
  intermediate programmes admit to 1st Year (HSSC Part-I), BS programmes to the 1st semester.
--}}
@props(['name', 'label', 'programs', 'value' => null, 'required' => false, 'empty' => 'Choose a programme…', 'hint' => null])
@php
    $current = old($name, $value);
    $groups = collect($programs)->groupBy(fn ($p) => $p->level === 'intermediate' ? 'Intermediate — 1st Year (HSSC Part-I)' : 'BS — 1st Semester')->sortKeysDesc();
    $invalid = $errors->has($name);
@endphp
<div {{ $attributes->only('class') }}>
    <label for="f_{{ $name }}" class="field-label">{{ $label }} @if ($required)<span class="req">*</span>@endif</label>
    <select id="f_{{ $name }}" name="{{ $name }}" class="input" @if($required) required @endif @if($invalid) aria-invalid="true" @endif {{ $attributes->except('class') }}>
        <option value="">{{ $empty }}</option>
        @foreach ($groups as $group => $items)
            <optgroup label="{{ $group }}">
                @foreach ($items as $p)
                    <option value="{{ $p->id }}" @selected((string) $current === (string) $p->id)>{{ $p->name }}</option>
                @endforeach
            </optgroup>
        @endforeach
    </select>
    @error($name)
        <div class="field-error">{{ $message }}</div>
    @else
        @if ($hint)<div class="field-hint">{{ $hint }}</div>@endif
    @enderror
</div>
