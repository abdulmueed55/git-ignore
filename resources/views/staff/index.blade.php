@php
    $roleTone = ['admin' => 'bad', 'principal' => 'brass', 'admission_officer' => 'info', 'teacher' => 'brand', 'editor' => 'warn'];
@endphp
<x-layouts.app title="Staff & roles">
    <x-ui.page-header eyebrow="Administration" title="Staff & roles" description="Who can sign in, what they can see, and the teaching staff of each department.">
        <x-slot:actions><a href="{{ route('staff.create') }}" class="btn btn-primary"><x-icon name="plus" :size="16" /> Add staff member</a></x-slot:actions>
    </x-ui.page-header>

    @if ($cred = session('credentials'))
        <div class="panel mb-6 flex flex-wrap items-center gap-4 border-brass/50 p-5" x-data="{ copied: false }">
            <span class="grid h-10 w-10 place-items-center rounded-xl bg-brass-soft text-brass"><x-icon name="lock" :size="18" /></span>
            <div class="min-w-0 flex-1">
                <div class="text-[13.5px] font-semibold text-ink">Temporary sign-in for {{ $cred['name'] }}</div>
                <div class="text-[12.5px] text-muted">Shown only once. Share it privately — the password can be reset again from this page.</div>
            </div>
            <div class="mono rounded-lg border border-line bg-sunken px-3 py-2 text-[12.5px]">
                <span class="text-muted">{{ $cred['email'] }}</span> · <span class="font-semibold text-ink">{{ $cred['password'] }}</span>
            </div>
            <button class="btn btn-ghost btn-sm" @click="navigator.clipboard?.writeText(@js($cred['email'].' / '.$cred['password'])); copied = true">
                <x-icon name="check" :size="14" /> <span x-text="copied ? 'Copied' : 'Copy'"></span>
            </button>
        </div>
    @endif

    <div class="mb-6 grid grid-cols-2 gap-3 md:grid-cols-3 xl:grid-cols-6">
        @foreach (\App\Models\User::ROLES as $key => $label)
            <a href="{{ route('staff.index', ['role' => $key]) }}" class="panel p-4 transition hover:border-line-strong {{ request('role') === $key ? 'ring-1 ring-brass' : '' }}">
                <span class="badge badge-{{ $roleTone[$key] }}">{{ $label }}</span>
                <div class="numeral mt-3 text-[28px]">{{ $roleCounts[$key] ?? 0 }}</div>
            </a>
        @endforeach
        <div class="panel p-4">
            <span class="badge">Inactive</span>
            <div class="numeral mt-3 text-[28px] {{ $inactive ? 'text-bad' : 'text-muted' }}">{{ $inactive }}</div>
        </div>
    </div>

    <div class="panel overflow-hidden">
        <form method="GET" class="flex flex-wrap gap-2 border-b border-line p-4" x-data>
            <div class="relative min-w-[240px] flex-1">
                <x-icon name="search" :size="16" class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-muted" />
                <input name="q" value="{{ request('q') }}" class="input pl-9" placeholder="Name, email or employee no">
            </div>
            <select name="department" class="input !w-auto min-w-[200px]" @change="$el.form.submit()">
                <option value="">All departments</option>
                @foreach ($departments as $id => $n)<option value="{{ $id }}" @selected(request('department') == $id)>{{ $n }}</option>@endforeach
            </select>
            @if (request('role'))<input type="hidden" name="role" value="{{ request('role') }}">@endif
            @if (request()->hasAny(['q', 'role', 'department']))<a href="{{ route('staff.index') }}" class="btn btn-ghost">Clear</a>@endif
        </form>
        @if ($staff->count())
            <div class="overflow-x-auto">
                <table class="table">
                    <thead><tr><th>Staff member</th><th>Role</th><th class="hidden lg:table-cell">Department</th><th class="hidden 2xl:table-cell">Employee no</th><th class="num hidden 2xl:table-cell">Courses</th><th class="hidden xl:table-cell">Last sign-in</th><th>Access</th><th></th></tr></thead>
                    <tbody>
                    @foreach ($staff as $u)
                        @php $locked = $u->isAdmin() && ! auth()->user()->isAdmin(); @endphp
                        <tr class="{{ $u->is_active ? '' : 'opacity-60' }}">
                            <td>
                                <div class="flex items-center gap-3">
                                    <x-ui.avatar :name="$u->name" :size="34" />
                                    <div class="min-w-0">
                                        <div class="truncate font-semibold text-ink">{{ $u->name }}</div>
                                        <div class="truncate text-[12px] text-muted">{{ $u->designation }} · <span class="mono">{{ $u->employee_no }}</span></div><div class="truncate text-[11.5px] text-muted">{{ $u->email }}</div>
                                    </div>
                                </div>
                            </td>
                            <td><span class="badge badge-{{ $roleTone[$u->role] ?? '' }}">{{ $u->roleLabel() }}</span></td>
                            <td class="hidden lg:table-cell">{{ $u->department?->name ?? '—' }}</td>
                            <td class="mono hidden text-[12px] 2xl:table-cell">{{ $u->employee_no }}</td>
                            <td class="num hidden 2xl:table-cell">{{ $u->courses_count ?: '—' }}</td>
                            <td class="hidden whitespace-nowrap text-[12px] xl:table-cell">{{ $u->last_login_at?->diffForHumans() ?? 'Never' }}</td>
                            <td>
                                @if ($locked || $u->id === auth()->id())
                                    <x-ui.status :value="$u->is_active ? 'active' : 'closed'" :label="$u->is_active ? 'Active' : 'Disabled'" />
                                @else
                                    <form method="POST" action="{{ route('staff.toggle', $u) }}">
                                        @csrf @method('PATCH')
                                        <button class="group inline-flex items-center gap-2 text-[12px]" title="{{ $u->is_active ? 'Disable sign-in' : 'Enable sign-in' }}">
                                            <span class="relative h-5 w-9 rounded-full transition {{ $u->is_active ? 'bg-ok' : 'bg-line-strong' }}">
                                                <span class="absolute top-0.5 h-4 w-4 rounded-full bg-white shadow transition {{ $u->is_active ? 'left-[18px]' : 'left-0.5' }}"></span>
                                            </span>
                                            <span class="{{ $u->is_active ? 'text-ok' : 'text-muted' }}">{{ $u->is_active ? 'Active' : 'Disabled' }}</span>
                                        </button>
                                    </form>
                                @endif
                            </td>
                            <td class="text-right">
                                @unless ($locked)
                                    <div class="flex justify-end gap-1">
                                        <a href="{{ route('staff.edit', $u) }}" class="btn btn-ghost btn-sm" title="Edit"><x-icon name="edit" :size="14" /></a>
                                        <x-ui.confirm :action="route('staff.reset-password', $u)" method="POST" confirm="Reset password?" title="Reset password"><x-icon name="lock" :size="14" /></x-ui.confirm>
                                    </div>
                                @endunless
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            <div class="border-t border-line px-4 py-3">{{ $staff->links() }}</div>
        @else
            <x-ui.empty icon="id" title="No staff match" description="Try another name or clear the filters.">
                <a href="{{ route('staff.index') }}" class="btn btn-ghost">Clear filters</a>
            </x-ui.empty>
        @endif
    </div>
</x-layouts.app>
