<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sign in — FG Liaquat Ali Degree College</title>
    <script>(() => { let t=null; try { t = localStorage.getItem('theme'); } catch (e) {} document.documentElement.dataset.theme = t || (matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light'); })();</script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-[#0a3d26]">
{{-- The whole sign-in screen sits on the full photo of the college building --}}
<div class="relative min-h-screen overflow-hidden">
    {{-- Whole building, full width; the lawn colour continues below so the screen is fully covered --}}
    <div class="absolute inset-0 bg-[linear-gradient(180deg,#5f6a2a_0%,#3f5226_35%,#173a22_75%,#0a2a17_100%)]"></div>
    <img src="{{ asset('images/college-building@2x.jpg') }}" alt="Front view of the FG Liaquat Ali Degree College building" class="absolute inset-x-0 top-0 h-auto min-h-[60vh] w-full object-cover object-[50%_55%] [mask-image:linear-gradient(to_bottom,black_78%,transparent_100%)]">
    <div class="absolute inset-0 bg-[linear-gradient(90deg,rgba(6,32,19,0.10)_0%,rgba(6,32,19,0)_45%,rgba(6,32,19,0.45)_100%)]"></div>
    <div class="absolute inset-x-0 bottom-0 h-[58%] bg-[linear-gradient(0deg,rgba(6,32,19,0.88)_0%,rgba(6,32,19,0.45)_55%,transparent_100%)]"></div>

    <div class="relative mx-auto flex min-h-screen max-w-[1400px] flex-col gap-8 px-5 py-6 lg:flex-row lg:items-stretch lg:justify-between lg:px-10 lg:py-10">
        <div class="flex flex-col justify-between text-white lg:max-w-[620px]">
            <div class="flex items-center gap-3 self-start rounded-[4px] bg-[#0a3d26]/85 py-2 pl-2 pr-4 backdrop-blur-sm">
                <x-crest class="h-12 w-11" />
                <div>
                    <div class="text-[17px] font-extrabold tracking-tight">FG Liaquat Ali Degree College</div>
                    <div class="text-[12.5px] text-white/80">Peshawar Road, Rawalpindi</div>
                </div>
            </div>
            <div class="mt-[38vh] hidden lg:block">
                <div class="text-[12px] font-bold uppercase tracking-[0.08em] text-white/85">Commitment · Devotion · Progress</div>
                <h1 class="mt-3 text-[38px] font-extrabold leading-[1.1] tracking-tight">College ERP for admissions, attendance and the website.</h1>
                <p class="mt-3 text-[15px] leading-relaxed text-white/85">Documents are read and cross-checked automatically, registers are marked in seconds, and every notice on the college website is published from here.</p>
                <div class="mt-6 grid grid-cols-3 gap-6 border-t border-white/25 pt-5 text-[13px] text-white/80">
                    <div><div class="text-[20px] font-extrabold text-white">Admissions</div>AI document checks</div>
                    <div><div class="text-[20px] font-extrabold text-white">Attendance</div>P · A · L · Leave</div>
                    <div><div class="text-[20px] font-extrabold text-white">Website</div>notices &amp; merit lists</div>
                </div>
            </div>
        </div>

        <section class="flex items-center justify-center lg:justify-end">
            <div class="w-full max-w-[420px] rounded-md bg-surface p-7 text-ink shadow-[0_24px_60px_rgba(0,0,0,0.35)] sm:p-9">
                <div class="w-full max-w-[380px]">
            <div class="mb-6 flex items-center gap-3 lg:hidden">
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

            @if (app()->environment('local') || config('app.demo'))
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
            </div>
        </section>
    </div>
</div>
</body>
</html>
