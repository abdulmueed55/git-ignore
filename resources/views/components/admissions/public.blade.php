@props(['title' => 'Apply online', 'step' => null])
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title }} — FG Liaquat Ali Degree College Admissions</title>
    <meta name="description" content="Apply online for admission to FG Liaquat Ali Degree College, Peshawar Road, Rawalpindi. Upload your documents and track your application.">
    <script>
        (() => { let t = null; try { t = localStorage.getItem('theme'); } catch (e) {}
          document.documentElement.dataset.theme = t || (matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light'); })();
    </script>
    @stack('alpine')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen">
    <header class="border-b border-line bg-surface/80 backdrop-blur">
        <div class="mx-auto flex h-16 max-w-[1120px] items-center gap-3 px-4 sm:px-6">
            <a href="{{ url('/') }}" class="flex items-center gap-3">
                <x-crest class="h-9 w-9" />
                <div class="leading-tight">
                    <div class="display text-[15px] font-semibold text-ink">FG Liaquat Ali Degree College</div>
                    <div class="text-[11px] text-muted">Online admissions · Peshawar Road, Rawalpindi</div>
                </div>
            </a>
            <nav class="ml-auto flex items-center gap-1 text-[13px]">
                <a href="{{ route('apply.start') }}" class="hidden rounded-lg px-3 py-2 font-semibold text-ink-2 hover:bg-sunken sm:block">Apply</a>
                <a href="{{ route('apply.status') }}" class="whitespace-nowrap rounded-lg px-3 py-2 font-semibold text-ink-2 hover:bg-sunken">Check status</a>
                <button class="btn btn-ghost btn-icon !h-9 !w-9" title="Toggle theme" @click="setTheme(document.documentElement.dataset.theme === 'dark' ? 'light' : 'dark')">
                    <x-icon name="moon" :size="15" class="dark:hidden" /><x-icon name="sun" :size="15" class="hidden dark:block" />
                </button>
            </nav>
        </div>
    </header>

    @if ($step)
        @php $steps = [1 => 'Programme', 2 => 'Documents', 3 => 'Review', 4 => 'Submitted']; @endphp
        <div class="border-b border-line bg-surface">
            <ol class="mx-auto flex max-w-[1120px] items-center gap-2 overflow-x-auto px-4 py-3 sm:px-6">
                @foreach ($steps as $n => $label)
                    <li class="flex shrink-0 items-center gap-2">
                        <span class="grid h-6 w-6 place-items-center rounded-full text-[11px] font-bold {{ $n < $step ? 'bg-brand text-brand-ink' : ($n === $step ? 'bg-brass text-[#1b1405]' : 'bg-sunken text-muted') }}">
                            @if ($n < $step)<x-icon name="check" :size="12" />@else{{ $n }}@endif
                        </span>
                        <span class="text-[12.5px] font-semibold {{ $n === $step ? 'text-ink' : 'text-muted' }}">{{ $label }}</span>
                        @if ($n < 4)<span class="mx-1 h-px w-6 bg-line-strong sm:w-12"></span>@endif
                    </li>
                @endforeach
            </ol>
        </div>
    @endif

    <main class="mx-auto max-w-[1120px] px-4 py-8 sm:px-6 sm:py-10">
        @if (session('error'))
            <div class="mb-5 flex items-start gap-3 rounded-xl border border-bad/30 bg-bad-soft px-4 py-3 text-[13px] text-bad"><x-icon name="alert" :size="16" class="mt-0.5" /> {{ session('error') }}</div>
        @endif
        @if (session('success'))
            <div class="mb-5 flex items-start gap-3 rounded-xl border border-ok/30 bg-ok-soft px-4 py-3 text-[13px] text-ok"><x-icon name="check" :size="16" class="mt-0.5" /> {{ session('success') }}</div>
        @endif
        {{ $slot }}
    </main>

    <footer class="border-t border-line">
        <div class="mx-auto flex max-w-[1120px] flex-wrap items-center justify-between gap-2 px-4 py-5 text-[12px] text-muted sm:px-6">
            <span>FG Liaquat Ali Degree College, Peshawar Road, Rawalpindi · Admission office 051-5492888</span>
            <span>Your documents are stored privately and seen only by the admission office.</span>
        </div>
    </footer>
</body>
</html>
