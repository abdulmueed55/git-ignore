<x-portal.layout :title="$page->title" :description="$page->meta_description" :menu="$menu" :s="$s">
    <div class="border-b border-line bg-surface">
        <div class="mx-auto max-w-3xl px-4 py-12">
            <div class="eyebrow !text-brass">{{ $s['college_name'] ?? '' }}</div>
            <h1 class="display mt-3 text-[34px] leading-tight text-ink sm:text-[42px]">{{ $page->title }}</h1>
            @if ($page->summary)<p class="mt-3 text-[16px] text-ink-2">{{ $page->summary }}</p>@endif
        </div>
    </div>
    <article class="mx-auto max-w-3xl px-4 pt-10">
        <div class="prose-ledger !text-[15.5px] [&_table]:w-full [&_td]:border-b [&_td]:border-line [&_td]:py-2 [&_th]:border-b [&_th]:border-line-strong [&_th]:py-2 [&_th]:text-left">{!! $page->body !!}</div>
        @if ($page->slug === 'contact')
            <div class="mt-8 grid gap-4 rounded-xl border border-line bg-surface p-6 text-[14px] sm:grid-cols-2">
                @foreach (['address' => ['map', 'Address'], 'phone' => ['phone', 'Phone'], 'phone_admissions' => ['phone', 'Admissions'], 'email' => ['mail', 'Email'], 'office_hours' => ['clock', 'Office hours']] as $k => [$icon, $label])
                    @if (! empty($s[$k]))<div class="flex gap-3"><x-icon :name="$icon" class="mt-0.5 text-brass" /><div><div class="eyebrow">{{ $label }}</div><div class="text-ink">{{ $s[$k] }}</div></div></div>@endif
                @endforeach
            </div>
        @endif
        <p class="mt-10 text-[12px] text-muted">Last updated {{ $page->updated_at->format('j F Y') }}</p>
    </article>
</x-portal.layout>
