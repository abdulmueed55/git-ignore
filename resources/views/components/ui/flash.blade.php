@php
    $msg = session('success') ?? session('status');
    $err = session('error');
@endphp
@if ($msg || $err)
    <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 6000)" x-transition
         class="rise fixed bottom-5 right-5 z-50 flex max-w-sm items-start gap-3 rounded-xl border px-4 py-3 text-[13px] shadow-lg {{ $err ? 'border-bad/30 bg-bad-soft text-bad' : 'border-ok/30 bg-surface text-ink' }}">
        <span class="mt-0.5 grid h-5 w-5 place-items-center rounded-full {{ $err ? 'bg-bad' : 'bg-ok' }} text-white">
            <x-icon :name="$err ? 'x' : 'check'" :size="12" />
        </span>
        <div class="flex-1 font-medium">{{ $err ?? $msg }}</div>
        <button @click="show=false" class="text-muted hover:text-ink"><x-icon name="x" :size="14" /></button>
    </div>
@endif
