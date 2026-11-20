<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sign in — FG Liaquat Ali Degree College</title>
    <script>(() => { let t=null; try { t = localStorage.getItem('theme'); } catch (e) {} document.documentElement.dataset.theme = t || (matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light'); })();</script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen">
<div class="grid min-h-screen lg:grid-cols-[1.05fr_1fr]">
    <section class="relative hidden overflow-hidden bg-rail text-rail-ink lg:flex lg:flex-col lg:justify-between lg:p-12">
        <div class="pointer-events-none absolute inset-0 opacity-[0.07]" style="background-image: repeating-linear-gradient(to bottom, transparent 0 31px, #fff 31px 32px), linear-gradient(90deg, transparent 0 72px, #d4a64a 72px 73px, transparent 73px);"></div>
        <div class="relative flex items-center gap-3">
            <x-crest class="h-12 w-12" />
            <div>
                <div class="display text-[18px]">FG Liaquat Ali Degree College</div>
                <div class="text-[12px] text-rail-muted">Rawalpindi Cantt · Federal Government Educational Institutions</div>
            </div>
        </div>
        <div class="relative max-w-lg">
            <div class="eyebrow !text-brass">College ERP</div>
            <h1 class="display mt-3 text-[46px] leading-[1.05]">Admissions, attendance and the website — <em class="text-brass">in one ledger.</em></h1>
            <p class="mt-5 text-[14.5px] leading-relaxed text-rail-muted">Documents are read and cross-checked automatically, registers are marked in seconds, and every notice on the public site is published from here.</p>
        </div>
        <div class="relative grid grid-cols-3 gap-6 border-t border-rail-line pt-6 text-[12px] text-rail-muted">
            <div><div class="numeral text-[26px] text-rail-ink">AI</div>document reading</div>
            <div><div class="numeral text-[26px] text-rail-ink">4-tap</div>attendance marking</div>
            <div><div class="numeral text-[26px] text-rail-ink">1</div>place to publish</div>
        </div>
    </section>

    <section class="flex items-center justify-center px-5 py-12">
        <div class="w-full max-w-[380px]">
            <div class="mb-8 flex items-center gap-3 lg:hidden">
                <x-crest class="h-10 w-10" />
                <div class="display text-[17px]">FG Liaquat Ali Degree College</div>
            </div>
            <div class="eyebrow">Staff sign-in</div>
            <h2 class="display mt-2 text-[30px]">Welcome back</h2>
            <p class="mt-1 text-[13.5px] text-muted">Use the email issued by the college office.</p>

            <form method="POST" action="{{ route('login') }}" class="mt-8 space-y-4">
                @csrf
                <x-ui.field name="email" type="email" label="Email" required autofocus autocomplete="username" placeholder="name@fglacollege.edu.pk" />
                <x-ui.field name="password" type="password" label="Password" required autocomplete="current-password" placeholder="••••••••" />
                <label class="flex items-center gap-2 text-[13px] text-ink-2"><input type="checkbox" name="remember" class="checkbox"> Keep me signed in on this device</label>
                <button class="btn btn-primary h-11 w-full text-[14px]">Sign in <x-icon name="arrow-right" :size="16" /></button>
            </form>

            @if (app()->environment('local'))
                <div class="mt-8 rounded-xl border border-dashed border-line-strong p-4 text-[12px]">
                    <div class="eyebrow mb-2">Demo accounts · password <span class="mono normal-case tracking-normal text-ink">password</span></div>
                    <ul class="mono space-y-1 text-ink-2">
                        <li>principal@fglacollege.edu.pk</li>
                        <li>admissions@fglacollege.edu.pk</li>
                        <li>teacher@fglacollege.edu.pk</li>
                        <li>editor@fglacollege.edu.pk</li>
                        <li>admin@fglacollege.edu.pk</li>
                    </ul>
                </div>
            @endif

            <div class="mt-8 flex justify-between text-[12.5px]">
                <a href="{{ url('/') }}" class="text-muted hover:text-ink">← College portal</a>
                @if (Route::has('apply.start'))<a href="{{ route('apply.start') }}" class="font-semibold text-brand hover:underline">Apply for admission</a>@endif
            </div>
        </div>
    </section>
</div>
</body>
</html>
