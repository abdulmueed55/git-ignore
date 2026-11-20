<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>ID card — {{ $student->name }}</title>
    @vite(['resources/css/app.css'])
    <style>
        @page { size: A4; margin: 16mm; }
        @media print { .no-print { display: none !important; } body { background: white !important; } }
        .card { width: 86mm; height: 54mm; }
    </style>
</head>
<body class="grid min-h-screen place-items-center bg-sunken p-6">
<div class="flex flex-col items-center gap-6">
    <div class="no-print flex gap-2">
        <button onclick="window.print()" class="btn btn-primary"><x-icon name="printer" :size="15" /> Print card</button>
        <a href="{{ route('students.show', $student) }}" class="btn btn-ghost">Back to profile</a>
    </div>
    <div class="flex flex-wrap justify-center gap-6">
        {{-- Front --}}
        <div class="card relative overflow-hidden rounded-[10px] border border-line-strong bg-surface shadow-lg">
            <div class="flex items-center gap-2 bg-rail px-3 py-2 text-rail-ink">
                <x-crest class="h-7 w-7" />
                <div class="leading-tight">
                    <div class="display text-[11px]">FG Liaquat Ali Degree College</div>
                    <div class="text-[7.5px] text-rail-muted">Rawalpindi Cantt · Student identity card</div>
                </div>
            </div>
            <div class="flex gap-3 px-3 pt-2.5">
                <div class="grid h-[24mm] w-[19mm] shrink-0 place-items-center rounded-md border border-line bg-brand-soft">
                    <span class="display text-[20px] text-brand">{{ $student->initials() }}</span>
                </div>
                <div class="min-w-0 text-[8.5px] leading-snug">
                    <div class="display truncate text-[12.5px] text-ink">{{ $student->name }}</div>
                    <div class="truncate text-muted">{{ $student->gender === 'female' ? 'd/o' : 's/o' }} {{ $student->father_name }}</div>
                    <div class="mt-1.5 grid grid-cols-[34px_1fr] gap-x-1 gap-y-0.5">
                        <span class="text-muted">Roll</span><span class="mono font-semibold text-ink">{{ $student->roll_no }}</span>
                        <span class="text-muted">Class</span><span class="truncate text-ink">{{ $student->program?->code }} · {{ $student->section?->name }}</span>
                        <span class="text-muted">Session</span><span class="text-ink">{{ $student->academicSession?->name }}</span>
                        <span class="text-muted">Blood</span><span class="font-semibold text-bad">{{ $student->blood_group }}</span>
                    </div>
                </div>
            </div>
            <div class="absolute inset-x-0 bottom-0 h-1.5 bg-brass"></div>
        </div>
        {{-- Back --}}
        <div class="card relative overflow-hidden rounded-[10px] border border-line-strong bg-surface p-3 text-[8px] leading-relaxed text-ink-2 shadow-lg">
            <div class="eyebrow !text-[7px]">If found, please return to</div>
            <div class="mt-0.5 font-semibold text-ink">FG Liaquat Ali Degree College, Rawalpindi Cantt</div>
            <div class="mt-2 grid grid-cols-[52px_1fr] gap-y-0.5">
                <span class="text-muted">CNIC/B-Form</span><span class="mono">{{ $student->cnic }}</span>
                <span class="text-muted">Guardian</span><span class="mono">{{ $student->guardian_phone }}</span>
                <span class="text-muted">Address</span><span class="truncate">{{ $student->address }}, {{ $student->city }}</span>
                <span class="text-muted">Valid till</span><span>{{ $student->academicSession?->ends_on?->format('M Y') }}</span>
            </div>
            <div class="absolute inset-x-3 bottom-3 flex items-end justify-between">
                <div class="mono flex h-6 items-end gap-[1px]" aria-hidden="true">
                    @foreach (str_split(md5($student->roll_no)) as $ch)
                        <span class="bg-ink" style="width: {{ hexdec($ch) % 3 + 1 }}px; height: {{ 14 + hexdec($ch) % 10 }}px"></span>
                    @endforeach
                </div>
                <div class="text-center"><div class="h-px w-16 bg-ink-2"></div><div class="mt-0.5 text-[7px] text-muted">Principal</div></div>
            </div>
        </div>
    </div>
</div>
</body>
</html>
