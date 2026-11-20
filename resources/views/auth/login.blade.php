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
    <section class="relative hidden flex-col overflow-hidden bg-rail text-white lg:flex">
        <figure class="relative m-0">
            <img src="{{ asset('images/college-building@2x.jpg') }}" alt="Front view of the FG Liaquat Ali Degree College building" class="block aspect-[634/240] w-full object-cover">
            <div class="absolute inset-x-0 bottom-0 h-24 bg-gradient-to-t from-[#0a3d26] to-transparent"></div>
            <div class="absolute left-10 top-8 flex items-center gap-3 rounded-[4px] bg-[#0a3d26]/85 py-2 pl-2 pr-4 backdrop-blur-sm">
                <x-crest class="h-12 w-11" />
                <div>
                    <div class="text-[17px] font-extrabold tracking-tight">FG Liaquat Ali Degree College</div>
                    <div class="text-[12.5px] text-white/80">Peshawar Road, Rawalpindi</div>
                </div>
            </div>
        </figure>
        <div class="flex flex-1 flex-col justify-between px-10 pb-10 pt-6">
            <div class="max-w-xl">
                <div class="text-[12px] font-bold uppercase tracking-[0.08em] text-white/75">Commitment · Devotion · Progress</div>
                <h1 class="mt-3 text-[38px] font-extrabold leading-[1.1] tracking-tight">College ERP for admissions, attendance and the website.</h1>
                <p class="mt-4 text-[15px] leading-relaxed text-white/80">Documents are read and cross-checked automatically, registers are marked in seconds, and every notice on the college website is published from here.</p>
            </div>
            <div class="mt-8 grid grid-cols-3 gap-6 border-t border-white/20 pt-6 text-[13px] text-white/75">
                <div><div class="text-[22px] font-extrabold text-white">Admissions</div>AI document checks</div>
                <div><div class="text-[22px] font-extrabold text-white">Attendance</div>P · A · L · Leave</div>
                <div><div class="text-[22px] font-extrabold text-white">Website</div>notices &amp; merit lists</div>
            </div>
        </div>
    </section>

    <section class="flex items-center justify-center px-5 py-12">
        <div class="w-full max-w-[380px]">
            <div class="mb-8 flex items-center gap-3 lg:hidden">
                <x-crest class="h-10 w-10" />
                <div class="display text-[17px]">FG Liaquat Ali Degree College</div>
            </div>
            <div class="eyebrow">Staff sign-in</div>
            <h2 class="display mt-2 text-[30px] font-extrabold">Welcome back</h2>
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
