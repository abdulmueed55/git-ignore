{{-- Destructive action button with inline confirm step. --}}
@props(['action', 'method' => 'DELETE', 'label' => 'Delete', 'confirm' => 'Are you sure?'])
<form method="POST" action="{{ $action }}" x-data="{ ask: false }" class="inline">
    @csrf @method($method)
    <button type="button" x-show="!ask" @click="ask=true" {{ $attributes->merge(['class' => 'btn btn-ghost btn-sm']) }}>{{ $slot->isEmpty() ? $label : $slot }}</button>
    <span x-show="ask" x-cloak class="inline-flex items-center gap-1.5">
        <span class="text-[12px] text-muted">{{ $confirm }}</span>
        <button class="btn btn-danger btn-sm">Yes</button>
        <button type="button" class="btn btn-ghost btn-sm" @click="ask=false">No</button>
    </span>
</form>
