<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#0a3d26">
    <link rel="manifest" href="{{ asset('student.webmanifest') }}">
    <link rel="apple-touch-icon" href="{{ asset('images/app-icon-192.png') }}">
    <title>Student portal — FG Liaquat Ali Degree College</title>
    <script @nonce>(() => { let t=null; try { t = localStorage.getItem('theme'); } catch (e) {} document.documentElement.dataset.theme = t || (matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light'); })();</script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-[#0a3d26]">
<div class="relative min-h-screen overflow-hidden">
    <div class="absolute inset-0 bg-[linear-gradient(180deg,#5f6a2a_0%,#3f5226_35%,#173a22_75%,#0a2a17_100%)]"></div>
    <img src="{{ asset('images/college-building@2x.jpg') }}" alt="Front view of the FG Liaquat Ali Degree College building" class="absolute inset-x-0 top-0 h-auto min-h-[45vh] w-full object-cover object-[50%_55%] [mask-image:linear-gradient(to_bottom,black_70%,transparent_100%)]">
    <div class="absolute inset-x-0 bottom-0 h-[65%] bg-[linear-gradient(0deg,rgba(6,32,19,0.92)_0%,rgba(6,32,19,0.5)_55%,transparent_100%)]"></div>

    <div class="relative mx-auto flex min-h-screen max-w-[480px] flex-col justify-end gap-6 px-5 pb-8 pt-6 sm:justify-center" style="padding-bottom: max(2rem, env(safe-area-inset-bottom))">
        <div class="flex items-center gap-3 self-start rounded-[4px] bg-[#0a3d26]/85 py-2 pl-2 pr-4 text-white backdrop-blur-sm">
            <x-crest class="h-11 w-10" />
            <div>
                <div class="text-[16px] font-extrabold tracking-tight">FG Liaquat Ali Degree College</div>
                <div class="text-[12px] text-white/80">Student portal</div>
            </div>
        </div>

        <div class="rounded-md bg-surface p-6 text-ink shadow-[0_24px_60px_rgba(0,0,0,0.35)] sm:p-8">
            <div class="eyebrow">Students</div>
            <h1 class="mt-1 text-[26px] font-extrabold">Sign in</h1>
            <p class="mt-1 text-[13.5px] text-muted">Attendance, timetable, extra classes, leave and your admission record.</p>

            @if (session('success'))<div class="mt-4 rounded-md bg-ok-soft px-3 py-2 text-[13px] text-ok">{{ session('success') }}</div>@endif

            <form method="POST" action="{{ route('student.login.store') }}" class="mt-6 space-y-4">
                @csrf
                <x-ui.field name="roll_no" label="Roll number" required autofocus autocomplete="username" autocapitalize="characters" placeholder="e.g. FSC-PM-26-001" />
                <x-ui.field name="password" type="password" label="Password" required autocomplete="current-password" placeholder="••••••••" />
                <label class="flex items-center gap-2 text-[13px] text-ink-2"><input type="checkbox" name="remember" class="checkbox"> Keep me signed in on this phone</label>
                <button class="btn btn-primary h-11 w-full text-[14px]">Sign in <x-icon name="arrow-right" :size="16" /></button>
            </form>

            <div class="mt-5 rounded-md border border-line bg-sunken/60 p-3 text-[12.5px] text-ink-2">
                <b class="text-ink">First time?</b> Your account is ready as soon as you are admitted. Sign in with your <b>roll number</b> and the <b>6-digit PIN</b> of your admission application, then choose your own password.
                Pehli dafa: roll number aur admission wala PIN. Bhool gaye to college office se naya password lein.
            </div>

            @if ((app()->environment('local') || config('app.demo')) && ($demo = \App\Models\Student::where('status', 'active')->whereNotNull('password')->where('must_change_password', false)->orderBy('id')->value('roll_no')))
                <div class="mt-4 rounded-md border border-dashed border-line-strong p-3 text-[12px]">
                    <div class="eyebrow mb-1">Demo student</div>
                    <span class="mono text-ink">{{ $demo }}</span> · password <span class="mono text-ink">password</span>
                </div>
            @endif

            <div class="mt-6 flex justify-between text-[12.5px]">
                <a href="{{ url('/') }}" class="text-muted hover:text-ink">← College website</a>
                <a href="{{ route('apply.status') }}" class="font-semibold text-brand hover:underline">Admission status</a>
            </div>
        </div>
    </div>
</div>
</body>
</html>
