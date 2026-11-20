{{--
  <x-ui.field name="title" label="Title" required />
  <x-ui.field name="body" label="Body" type="textarea" />
  <x-ui.field name="program_id" label="Program" type="select" :options="[id => label]" />
--}}
@props(['name', 'label' => null, 'type' => 'text', 'value' => null, 'options' => [], 'required' => false, 'hint' => null, 'placeholder' => null, 'autofilled' => false, 'empty' => 'Select…'])
@php
    $dot = str_replace(['[', ']'], ['.', ''], $name);
    $id = 'f_'.str_replace(['.', '[', ']'], '_', $name);
    $current = old($dot, $value);
    $invalid = $errors->has($dot);
    $cls = 'input'.($autofilled ? ' is-autofilled' : '');
@endphp
<div {{ $attributes->only('class') }}>
    @if ($label)
        <label for="{{ $id }}" class="field-label">
            {{ $label }} @if ($required)<span class="req">*</span>@endif
            @if ($autofilled)<span class="badge badge-brass badge-plain ml-auto !h-[18px] !text-[10px]"><x-icon name="sparkles" :size="10" /> From documents</span>@endif
        </label>
    @endif
    @if ($type === 'textarea')
        <textarea id="{{ $id }}" name="{{ $name }}" class="{{ $cls }}" @if($required) required @endif @if($invalid) aria-invalid="true" @endif placeholder="{{ $placeholder }}" {{ $attributes->except('class') }}>{{ $current }}</textarea>
    @elseif ($type === 'select')
        <select id="{{ $id }}" name="{{ $name }}" class="{{ $cls }}" @if($required) required @endif @if($invalid) aria-invalid="true" @endif {{ $attributes->except('class') }}>
            @if ($empty !== false)<option value="">{{ $empty }}</option>@endif
            @foreach ($options as $k => $v)
                <option value="{{ $k }}" @selected((string) $current === (string) $k)>{{ $v }}</option>
            @endforeach
        </select>
    @else
        <input id="{{ $id }}" type="{{ $type }}" name="{{ $name }}" value="{{ $type === 'password' ? '' : $current }}" class="{{ $cls }}" @if($required) required @endif @if($invalid) aria-invalid="true" @endif placeholder="{{ $placeholder }}" {{ $attributes->except('class') }}>
    @endif
    @error($dot)
        <div class="field-error">{{ $message }}</div>
    @else
        @if ($hint)<div class="field-hint">{{ $hint }}</div>@endif
    @enderror
</div>
