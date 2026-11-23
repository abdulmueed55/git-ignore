<x-layouts.student title="Leave">
    <div class="eyebrow">Leave</div>
    <h1 class="mt-1 text-[24px] font-extrabold">Ask for leave</h1>
    <p class="mt-1 text-[13.5px] text-muted">The principal’s office decides. Once approved, your absences in those days are counted as leave, not absent.</p>

    <div class="mt-5 grid gap-5 lg:grid-cols-[1fr_360px]">
        <section class="panel order-2 overflow-hidden lg:order-1">
            <div class="panel-head"><div class="panel-title">My requests</div></div>
            @if ($leaves->isEmpty())
                <x-ui.empty icon="leaf" title="No leave requests yet" description="Use the form to ask for leave, for example when you are ill or have a family matter. Bring the medical certificate to the office if you have one.">
                    <a href="#ask" class="btn btn-primary btn-sm">Ask for leave</a>
                </x-ui.empty>
            @else
                <ul class="divide-y divide-line">
                    @foreach ($leaves as $l)
                        <li class="flex items-start gap-3 px-4 py-3">
                            <div class="min-w-0 flex-1">
                                <div class="text-[13.5px] font-semibold">{{ $l->from_date->format('D j M') }}{{ $l->days() > 1 ? ' → '.$l->to_date->format('D j M') : '' }} <span class="font-normal text-muted">· {{ $l->days() }} {{ \Illuminate\Support\Str::plural('day', $l->days()) }}</span></div>
                                <div class="text-[12.5px] text-ink-2">{{ $l->reason }}</div>
                                @if ($l->decider)<div class="text-[11.5px] text-muted">{{ ucfirst($l->status) }} by {{ $l->decider->name }}</div>@endif
                            </div>
                            <x-ui.status :value="$l->status" />
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>

        <form id="ask" method="POST" action="{{ route('student.leave.store') }}" class="panel order-1 space-y-4 self-start p-5 lg:order-2">
            @csrf
            <div class="panel-title">New request</div>
            <div class="grid grid-cols-2 gap-3">
                <x-ui.field name="from_date" type="date" label="From" required :value="old('from_date', today()->toDateString())" />
                <x-ui.field name="to_date" type="date" label="To" required :value="old('to_date', today()->toDateString())" />
            </div>
            <x-ui.field name="reason" type="textarea" label="Reason" required rows="3" maxlength="255" placeholder="e.g. Fever since yesterday — will bring the CMH certificate" :value="old('reason')" />
            <button class="btn btn-primary h-11 w-full"><x-icon name="leaf" :size="15" /> Send request</button>
            <p class="text-[12px] text-muted">Up to 15 days per request, from 7 days back to 30 days ahead.</p>
        </form>
    </div>
</x-layouts.student>
