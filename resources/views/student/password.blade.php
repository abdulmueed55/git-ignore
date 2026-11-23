<x-layouts.student title="Password">
    <div class="mx-auto max-w-md">
        <div class="eyebrow">Account</div>
        <h1 class="mt-1 text-[24px] font-extrabold">{{ $student->must_change_password ? 'Choose your password' : 'Change password' }}</h1>
        <p class="mt-1 text-[13.5px] text-muted">
            @if ($student->must_change_password)
                Welcome, {{ $student->name }}. Before you continue, replace the admission PIN (or the temporary password from the office) with your own password.
            @else
                Use at least 8 characters with letters and numbers.
            @endif
        </p>
        <form method="POST" action="{{ route('student.password.update') }}" class="panel mt-5 space-y-4 p-5">
            @csrf @method('PUT')
            <x-ui.field name="current_password" type="password" :label="$student->must_change_password ? 'Admission PIN or temporary password' : 'Current password'" required autocomplete="current-password" />
            <x-ui.field name="password" type="password" label="New password" required autocomplete="new-password" hint="At least 8 characters, letters and numbers." />
            <x-ui.field name="password_confirmation" type="password" label="New password again" required autocomplete="new-password" />
            <button class="btn btn-primary h-11 w-full"><x-icon name="lock" :size="15" /> Save password</button>
        </form>
        @if ($student->must_change_password)
            <form method="POST" action="{{ route('student.logout') }}" class="mt-4 text-center">@csrf<button class="text-[13px] text-muted hover:text-ink">Sign out</button></form>
        @endif
    </div>
</x-layouts.student>
