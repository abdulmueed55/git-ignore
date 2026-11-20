<x-layouts.app :title="'Merit list #'.$list->list_no.' · '.$list->program->code">
    <x-ui.page-header :eyebrow="$list->cycle->title" :title="$list->program->name.' — merit list #'.$list->list_no"
        description="{{ $list->status === 'published' ? 'Published '.$list->published_at?->format('j M Y').' · fee deadline '.$list->fee_deadline?->format('j M Y') : 'Draft — not visible to applicants until published.' }}">
        <x-slot:actions>
            <a href="{{ route('admissions.merit.index', ['cycle' => $list->admission_cycle_id]) }}" class="btn btn-ghost"><x-icon name="arrow-left" :size="15" /> All lists</a>
            <a href="{{ route('admissions.merit.print', $list) }}" target="_blank" class="btn btn-ghost"><x-icon name="printer" :size="15" /> Print</a>
            @if ($list->status === 'draft')
                <x-ui.confirm :action="route('admissions.merit.destroy', $list)" label="Discard draft" confirm="Discard this draft?" />
            @endif
        </x-slot:actions>
    </x-ui.page-header>

    <x-admissions.subnav />

    <div class="grid grid-cols-1 gap-4 lg:grid-cols-[1fr_320px]">
        <div class="panel overflow-hidden">
            @if ($list->entries->isEmpty())
                <x-ui.empty title="This list is empty" description="No verified applicants were above the minimum when it was prepared. Discard it and prepare again later." icon="trophy" />
            @else
            <div class="overflow-x-auto">
                <table class="table">
                    <thead><tr><th class="num">#</th><th>Applicant</th><th>CNIC / B-Form</th><th>Quota</th><th class="num">Merit</th><th>Status</th></tr></thead>
                    <tbody>
                    @foreach ($list->entries as $e)
                        <tr>
                            <td class="num"><span class="numeral text-[18px] {{ $e->position <= 3 ? 'text-brass' : 'text-muted' }}">{{ $e->position }}</span></td>
                            <td>
                                <a href="{{ route('admissions.show', $e->application) }}" class="font-semibold text-ink hover:text-brand">{{ $e->application->name }}</a>
                                <div class="text-[11.5px] text-muted">{{ $e->application->gender === 'female' ? 'd/o' : 's/o' }} {{ $e->application->father_name }} · <span class="mono">{{ $e->application->tracking_no }}</span></div>
                            </td>
                            <td class="mono text-[12px]">{{ $e->application->cnic }}</td>
                            <td class="text-[12px]">{{ \App\Models\AdmissionApplication::QUOTAS[$e->application->quota] ?? $e->application->quota }}@if($e->application->is_hafiz) <span class="badge badge-brass badge-plain ml-1">Hafiz</span>@endif</td>
                            <td class="num mono font-semibold text-ink">{{ number_format($e->merit_score, 3) }}</td>
                            <td><x-ui.status :value="$e->application->status" :label="$e->application->statusLabel()" /></td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            @endif
        </div>

        <div class="space-y-4">
            <div class="panel p-5">
                <div class="eyebrow">Closing merit</div>
                <div class="numeral mt-2 text-[44px] text-ink">{{ $list->closing_merit ? number_format($list->closing_merit, 2) : '—' }}</div>
                <dl class="mt-4 space-y-2 border-t border-line pt-3 text-[12.5px]">
                    <div class="flex justify-between"><dt class="text-muted">Seats offered</dt><dd class="mono">{{ $list->seats_offered }}</dd></div>
                    <div class="flex justify-between"><dt class="text-muted">Programme seats</dt><dd class="mono">{{ $list->program->seats }}</dd></div>
                    <div class="flex justify-between"><dt class="text-muted">Highest merit</dt><dd class="mono">{{ $list->entries->max('merit_score') ? number_format($list->entries->max('merit_score'), 2) : '—' }}</dd></div>
                    <div class="flex justify-between"><dt class="text-muted">Minimum required</dt><dd class="mono">{{ rtrim(rtrim(number_format($list->program->min_percentage, 2), '0'), '.') }}%</dd></div>
                </dl>
            </div>
            @if ($list->status === 'draft' && $list->entries->isNotEmpty())
                <form method="POST" action="{{ route('admissions.merit.publish', $list) }}" class="panel space-y-3 border-brass/40 p-5">
                    @csrf
                    <div class="text-[13.5px] font-semibold text-ink">Publish this list</div>
                    <p class="text-[12.5px] text-muted">The {{ $list->entries->count() }} applicants become <b>Selected</b>; other eligible applicants are <b>Waitlisted</b> for the next list.</p>
                    <x-ui.field name="fee_deadline" label="Fee deposit deadline" type="date" required :value="now()->addDays(7)->format('Y-m-d')" />
                    <button class="btn btn-brass w-full"><x-icon name="megaphone" :size="15" /> Publish merit list</button>
                </form>
            @endif
        </div>
    </div>
</x-layouts.app>
