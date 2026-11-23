@php use App\Http\Controllers\Academics\StudentController; @endphp
<x-layouts.app :title="$student->name">
    <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
        <a href="{{ route('students.index') }}" class="inline-flex items-center gap-2 text-[13px] text-muted hover:text-ink"><x-icon name="arrow-left" :size="15" /> All students</a>
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('students.card', $student) }}" target="_blank" class="btn btn-ghost"><x-icon name="printer" :size="15" /> ID card</a>
            @if ($canEdit)<a href="{{ route('students.edit', $student) }}" class="btn btn-primary"><x-icon name="edit" :size="15" /> Edit record</a>@endif
        </div>
    </div>

    {{-- Identity header: styled like a college register entry --}}
    <section class="panel relative mb-6 overflow-hidden">
        <div class="pointer-events-none absolute inset-y-0 left-[118px] hidden w-px bg-bad/25 sm:block"></div>
        <div class="ruled pointer-events-none absolute inset-0 opacity-40"></div>
        <div class="relative grid gap-6 p-6 sm:grid-cols-[96px_1fr_auto] sm:p-7">
            <div class="grid h-24 w-24 place-items-center rounded-2xl border border-line bg-brand-soft">
                <span class="display text-[34px] text-brand">{{ $student->initials() }}</span>
            </div>
            <div class="min-w-0">
                <div class="eyebrow flex items-center gap-2">Register entry <span class="mono normal-case tracking-normal text-brass">{{ $student->roll_no }}</span></div>
                <h1 class="display mt-1 text-[30px] leading-tight sm:text-[34px]">{{ $student->name }}</h1>
                <div class="mt-1 text-[13.5px] text-muted">{{ $student->gender === 'female' ? 'd/o' : 's/o' }} <span class="text-ink-2">{{ $student->father_name }}</span></div>
                <div class="mt-4 flex flex-wrap gap-2">
                    <x-ui.status :value="$student->status" :label="StudentController::STATUSES[$student->status] ?? null" />
                    <span class="badge badge-brand badge-plain">{{ $student->program?->code }}</span>
                    <span class="badge badge-plain">{{ $student->section?->name ?? 'No section' }}</span>
                    <span class="badge badge-plain">Session {{ $student->academicSession?->name }}</span>
                </div>
            </div>
            <dl class="grid grid-cols-2 gap-x-8 gap-y-3 text-[12.5px] sm:grid-cols-1 sm:text-right">
                <div><dt class="eyebrow">Registration</dt><dd class="mono mt-0.5 text-ink">{{ $student->registration_no ?? '—' }}</dd></div>
                <div><dt class="eyebrow">CNIC / B-Form</dt><dd class="mono mt-0.5 text-ink">{{ $student->cnic }}</dd></div>
                <div><dt class="eyebrow">Enrolled</dt><dd class="mt-0.5 text-ink">{{ $student->created_at?->format('j M Y') }}</dd></div>
            </dl>
        </div>
    </section>

    <div class="grid gap-6 xl:grid-cols-[1fr_380px]">
        <div class="space-y-6">
            {{-- Attendance --}}
            <section class="panel">
                <div class="panel-head">
                    <div class="panel-title">Attendance</div>
                    @if ($attendance && Route::has('attendance.student'))
                        <a href="{{ route('attendance.student', $student) }}" class="btn btn-ghost btn-sm">Full report <x-icon name="arrow-right" :size="14" /></a>
                    @endif
                </div>
                @if ($attendance)
                    @php $tone = $attendance['percentage'] >= 75 ? 'ok' : ($attendance['percentage'] >= 60 ? 'warn' : 'bad'); @endphp
                    <div class="grid gap-6 p-5 md:grid-cols-[150px_1fr]">
                        <div class="flex flex-col items-center gap-2">
                            <x-ui.ring :value="$attendance['percentage']" :size="132" :tone="$tone" label="Overall" />
                            @if ($attendance['percentage'] < 75)
                                <span class="badge badge-bad">Below 75% requirement</span>
                            @else
                                <span class="badge badge-ok">Eligible for exams</span>
                            @endif
                        </div>
                        <div>
                            <div class="mb-4 grid grid-cols-4 gap-2 text-center">
                                @foreach (['present' => ['Present', 'ok'], 'late' => ['Late', 'warn'], 'leave' => ['Leave', 'info'], 'absent' => ['Absent', 'bad']] as $k => [$lbl, $t])
                                    <div class="rounded-lg bg-sunken px-2 py-2.5">
                                        <div class="numeral text-[22px] text-{{ $t }}">{{ $attendance[$k] }}</div>
                                        <div class="eyebrow mt-1 !text-[9.5px]">{{ $lbl }}</div>
                                    </div>
                                @endforeach
                            </div>
                            <div class="space-y-2.5">
                                @foreach ($bySubject as $row)
                                    <div class="grid grid-cols-[minmax(0,1fr)_120px_48px] items-center gap-3 text-[12.5px]">
                                        <span class="truncate text-ink-2">{{ $row['subject'] }}</span>
                                        <span class="h-1.5 overflow-hidden rounded-full bg-sunken">
                                            <span class="block h-full rounded-full {{ $row['pct'] >= 75 ? 'bg-ok' : ($row['pct'] >= 60 ? 'bg-warn' : 'bg-bad') }}" style="width: {{ $row['pct'] }}%"></span>
                                        </span>
                                        <span class="mono text-right text-ink">{{ $row['pct'] }}%</span>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                @else
                    <x-ui.empty icon="check" title="No lectures recorded yet"
                        description="Attendance will appear here as soon as a teacher marks the first lecture for {{ $student->section?->label() ?? 'this student’s section' }}.">
                        @if (Route::has('attendance.index'))<a href="{{ route('attendance.index') }}" class="btn btn-ghost">Go to attendance</a>@endif
                    </x-ui.empty>
                @endif
            </section>

            {{-- Courses --}}
            <section class="panel">
                <div class="panel-head">
                    <div class="panel-title">Courses this term</div>
                    @if ($student->section)
                        <a href="{{ route('academics.timetable', ['section' => $student->section_id]) }}" class="btn btn-ghost btn-sm"><x-icon name="clock" :size="14" /> Timetable</a>
                    @endif
                </div>
                @if ($student->section && $student->section->courses->count())
                    <div class="grid gap-px bg-line sm:grid-cols-2">
                        @foreach ($student->section->courses as $c)
                            <div class="flex items-center gap-3 bg-surface px-5 py-3.5">
                                <span class="mono w-16 shrink-0 text-[11.5px] text-brass">{{ $c->subject->code }}</span>
                                <div class="min-w-0 flex-1">
                                    <div class="truncate text-[13.5px] font-medium text-ink">{{ $c->subject->name }}</div>
                                    <div class="truncate text-[12px] text-muted">{{ $c->teacher?->name ?? 'Teacher not assigned' }}</div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <x-ui.empty icon="book" title="No courses assigned" description="Place the student in a section that has subjects and teachers assigned." />
                @endif
            </section>
        </div>

        <aside class="space-y-6">
            <section class="panel">
                <div class="panel-head"><div class="panel-title">Student portal</div>
                    <x-ui.status :value="$student->hasPortal() ? ($student->must_change_password ? 'pending' : 'active') : 'missing'" :label="$student->hasPortal() ? ($student->must_change_password ? 'First sign-in pending' : 'Active') : 'No password yet'" /></div>
                <div class="space-y-3 px-5 py-4 text-[13px]">
                    @if (session('portal_password'))
                        <div class="rounded-md border border-brass/40 bg-brass-soft px-3 py-2">
                            <div class="text-[12px] text-muted">Give this to the student (shown once). They must change it at first sign-in.</div>
                            <div class="mono mt-1 text-[15px] font-bold text-ink">{{ $student->roll_no }} · {{ session('portal_password') }}</div>
                        </div>
                    @endif
                    <p class="text-muted">Sign-in: roll number + password at <span class="mono text-ink">{{ route('student.login') }}</span>.
                        {{ $student->portal_last_login_at ? 'Last signed in '.$student->portal_last_login_at->diffForHumans().'.' : 'Not signed in yet.' }}</p>
                    @if ($canEdit)
                        <form method="POST" action="{{ route('students.portal-password', $student) }}" data-confirm="Give {{ $student->name }} a new temporary portal password? The old one stops working.">
                            @csrf
                            <button class="btn btn-ghost btn-sm w-full"><x-icon name="lock" :size="14" /> {{ $student->hasPortal() ? 'Reset portal password' : 'Create portal password' }}</button>
                        </form>
                    @endif
                </div>
            </section>

            <section class="panel">
                <div class="panel-head"><div class="panel-title">Contacts</div></div>
                <dl class="divide-y divide-line text-[13px]">
                    @foreach ([
                        ['Student phone', $student->phone, 'phone', true],
                        ['Guardian phone', $student->guardian_phone, 'phone', true],
                        ['Email', $student->email, 'mail', false],
                        ['Address', trim($student->address.', '.$student->city, ', '), 'map', false],
                    ] as [$label, $value, $icon, $mono])
                        <div class="flex items-start gap-3 px-5 py-3">
                            <x-icon :name="$icon" :size="15" class="mt-0.5 text-muted" />
                            <div class="min-w-0">
                                <dt class="eyebrow !text-[9.5px]">{{ $label }}</dt>
                                <dd class="{{ $mono ? 'mono' : '' }} mt-0.5 break-words text-ink">{{ $value ?: '—' }}</dd>
                            </div>
                        </div>
                    @endforeach
                </dl>
            </section>

            <section class="panel">
                <div class="panel-head"><div class="panel-title">Personal & placement</div></div>
                <dl class="grid grid-cols-2 gap-x-4 gap-y-4 p-5 text-[13px]">
                    <div><dt class="eyebrow !text-[9.5px]">Date of birth</dt><dd class="mt-0.5 text-ink">{{ $student->date_of_birth?->format('j M Y') ?? '—' }}</dd></div>
                    <div><dt class="eyebrow !text-[9.5px]">Age</dt><dd class="mt-0.5 text-ink">{{ $student->date_of_birth ? $student->date_of_birth->age.' years' : '—' }}</dd></div>
                    <div><dt class="eyebrow !text-[9.5px]">Gender</dt><dd class="mt-0.5 text-ink">{{ ucfirst($student->gender) }}</dd></div>
                    <div><dt class="eyebrow !text-[9.5px]">Blood group</dt><dd class="mono mt-0.5 text-bad">{{ $student->blood_group ?? '—' }}</dd></div>
                    <div><dt class="eyebrow !text-[9.5px]">Domicile</dt><dd class="mt-0.5 text-ink">{{ $student->domicile ?? '—' }}</dd></div>
                    <div><dt class="eyebrow !text-[9.5px]">Father CNIC</dt><dd class="mono mt-0.5 text-ink">{{ $student->father_cnic ?? '—' }}</dd></div>
                    <div class="col-span-2"><dt class="eyebrow !text-[9.5px]">Program</dt><dd class="mt-0.5 text-ink">{{ $student->program?->name }} <span class="text-muted">· {{ $student->program?->department?->name }}</span></dd></div>
                    <div class="col-span-2"><dt class="eyebrow !text-[9.5px]">Section incharge</dt><dd class="mt-0.5 text-ink">{{ $student->section?->incharge?->name ?? 'Not assigned' }}</dd></div>
                </dl>
                @if ($student->admission_application_id && Route::has('admissions.show'))
                    <div class="border-t border-line px-5 py-3">
                        <a href="{{ route('admissions.show', $student->admission_application_id) }}" class="inline-flex items-center gap-2 text-[13px] font-semibold text-brand hover:underline">
                            <x-icon name="files" :size="15" /> View admission application
                        </a>
                    </div>
                @endif
            </section>

            <section class="panel">
                <div class="panel-head"><div class="panel-title">Record history</div></div>
                @if ($activity->count())
                    <ol class="space-y-3 p-5 text-[12.5px]">
                        @foreach ($activity as $a)
                            <li class="flex gap-3">
                                <span class="mt-1.5 h-1.5 w-1.5 shrink-0 rounded-full bg-brass"></span>
                                <div><div class="text-ink-2">{{ $a->description }}</div><div class="text-muted">{{ $a->user?->name ?? 'System' }} · {{ $a->created_at->diffForHumans() }}</div></div>
                            </li>
                        @endforeach
                    </ol>
                @else
                    <p class="px-5 py-4 text-[12.5px] text-muted">No changes since enrolment. Edits to this record will be listed here with who made them.</p>
                @endif
            </section>
        </aside>
    </div>
</x-layouts.app>
