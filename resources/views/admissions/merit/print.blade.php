<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Merit list #{{ $list->list_no }} — {{ $list->program->code }}</title>
    @vite(['resources/css/app.css'])
    <style>@page { size: A4; margin: 14mm; } body { background: white; }</style>
</head>
<body class="p-8 text-ink print:p-0" onload="window.print()">
    <div class="mx-auto max-w-[780px]">
        <div class="flex items-center gap-4 border-b-2 border-ink pb-4">
            <x-crest class="h-14 w-14" />
            <div class="flex-1">
                <div class="display text-[22px]">FG Liaquat Ali Degree College, Peshawar Road, Rawalpindi</div>
                <div class="text-[13px] text-muted">{{ $list->cycle->title }} · Session {{ $list->cycle->academicSession?->name }}</div>
            </div>
            <div class="text-right">
                <div class="eyebrow">Merit list</div>
                <div class="numeral text-[34px]">#{{ $list->list_no }}</div>
            </div>
        </div>
        <div class="mt-5 flex justify-between text-[13px]">
            <div><b>{{ $list->program->name }}</b> ({{ $list->program->code }}) · {{ $list->program->admissionClass() }}</div>
            <div>Closing merit: <b class="mono">{{ $list->closing_merit ? number_format($list->closing_merit, 3) : '—' }}</b></div>
        </div>
        <table class="mt-4 w-full border-collapse text-[12.5px]">
            <thead><tr class="border-y border-ink text-left">
                <th class="py-2 pr-2">#</th><th class="py-2 pr-2">Tracking no</th><th class="py-2 pr-2">Name</th><th class="py-2 pr-2">Father's name</th><th class="py-2 text-right">Merit</th>
            </tr></thead>
            <tbody>
            @foreach ($list->entries as $e)
                <tr class="border-b border-line">
                    <td class="py-1.5 pr-2 mono">{{ $e->position }}</td>
                    <td class="py-1.5 pr-2 mono">{{ $e->application->tracking_no }}</td>
                    <td class="py-1.5 pr-2">{{ $e->application->name }}</td>
                    <td class="py-1.5 pr-2">{{ $e->application->father_name }}</td>
                    <td class="py-1.5 text-right mono">{{ number_format($e->merit_score, 3) }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
        <div class="mt-6 text-[12px] leading-relaxed text-ink-2">
            Selected candidates must deposit the fee by <b>{{ $list->fee_deadline?->format('l, j F Y') ?? '—' }}</b> with original documents for verification, failing which the seat passes to the next candidate.
            The college reserves the right to cancel admission if any document is found incorrect.
        </div>
        <div class="mt-14 flex justify-between text-[12px]">
            <div class="border-t border-ink pt-1">Admission Officer</div>
            <div class="border-t border-ink pt-1">Principal</div>
        </div>
        <div class="mt-8 text-[10.5px] text-muted">Printed {{ now()->format('j M Y, g:i a') }} · {{ $list->status === 'draft' ? 'DRAFT — not published' : 'Published '.$list->published_at?->format('j M Y') }}</div>
    </div>
</body>
</html>
