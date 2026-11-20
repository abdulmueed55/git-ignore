<?php

namespace App\Http\Controllers\Academics;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Department;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class StaffController extends Controller
{
    public const DESIGNATIONS = ['Principal', 'Vice Principal', 'Professor', 'Associate Professor', 'Assistant Professor', 'Lecturer', 'Admission Officer', 'Media & Website Coordinator', 'IT Administrator', 'Office Superintendent', 'Librarian'];

    public function index(Request $request): View
    {
        $staff = User::with('department')
            ->withCount('courses')
            ->when($request->filled('role'), fn ($q) => $q->where('role', $request->input('role')))
            ->when($request->filled('department'), fn ($q) => $q->where('department_id', $request->integer('department')))
            ->when($request->filled('q'), fn ($q) => $q->where(fn ($w) => $w->where('name', 'like', '%'.$request->q.'%')->orWhere('email', 'like', '%'.$request->q.'%')->orWhere('employee_no', 'like', '%'.$request->q.'%')))
            ->orderByRaw("case role when 'admin' then 0 when 'principal' then 1 when 'admission_officer' then 2 when 'editor' then 3 else 4 end")
            ->orderBy('name')
            ->paginate(30)->withQueryString();

        return view('staff.index', [
            'staff' => $staff,
            'roleCounts' => User::selectRaw('role, count(*) c')->groupBy('role')->pluck('c', 'role'),
            'departments' => Department::orderBy('name')->pluck('name', 'id'),
            'inactive' => User::where('is_active', false)->count(),
        ]);
    }

    public function create(Request $request): View
    {
        return view('staff.form', $this->formData($request, new User(['role' => 'teacher', 'is_active' => true, 'employee_no' => $this->nextEmployeeNo()])));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $password = Str::password(12, symbols: false);
        $user = User::create($data + ['password' => $password, 'is_active' => true]);
        ActivityLog::record('staff.created', "Created {$user->roleLabel()} account for {$user->name}", $user);

        return redirect()->route('staff.index')
            ->with('success', "Account created for {$user->name}.")
            ->with('credentials', ['name' => $user->name, 'email' => $user->email, 'password' => $password]);
    }

    public function edit(Request $request, User $staff): View
    {
        $this->guardTarget($request, $staff);

        return view('staff.form', $this->formData($request, $staff));
    }

    public function update(Request $request, User $staff): RedirectResponse
    {
        $this->guardTarget($request, $staff);
        $data = $this->validated($request, $staff);
        if ($staff->id === $request->user()->id && $data['role'] !== $staff->role) {
            return back()->with('error', 'You cannot change your own role.');
        }
        $staff->fill($data);
        $changed = array_keys($staff->getDirty());
        $staff->save();
        ActivityLog::record('staff.updated', "Updated staff record of {$staff->name}", $staff, ['changed' => $changed]);

        return redirect()->route('staff.index')->with('success', "{$staff->name}'s record updated.");
    }

    public function toggle(Request $request, User $staff): RedirectResponse
    {
        $this->guardTarget($request, $staff);
        if ($staff->id === $request->user()->id) {
            return back()->with('error', 'You cannot deactivate your own account.');
        }
        $staff->update(['is_active' => ! $staff->is_active]);
        ActivityLog::record($staff->is_active ? 'staff.activated' : 'staff.deactivated', ($staff->is_active ? 'Re-activated' : 'Deactivated')." the account of {$staff->name}", $staff);

        return back()->with('success', $staff->is_active ? "{$staff->name} can sign in again." : "{$staff->name} can no longer sign in.");
    }

    public function resetPassword(Request $request, User $staff): RedirectResponse
    {
        $this->guardTarget($request, $staff);
        $password = Str::password(12, symbols: false);
        $staff->update(['password' => $password]);
        ActivityLog::record('staff.password_reset', "Reset the password of {$staff->name}", $staff);

        return redirect()->route('staff.index')->with('success', "Password reset for {$staff->name}.")
            ->with('credentials', ['name' => $staff->name, 'email' => $staff->email, 'password' => $password]);
    }

    // ------------------------------------------------------------------

    /** Roles the current user may grant. Only an admin can create or edit admins. */
    private function assignableRoles(Request $request): array
    {
        $roles = User::ROLES;
        if (! $request->user()->isAdmin()) {
            unset($roles['admin']);
        }

        return $roles;
    }

    private function guardTarget(Request $request, User $staff): void
    {
        abort_if($staff->isAdmin() && ! $request->user()->isAdmin(), 403, 'Only an administrator can manage administrator accounts.');
    }

    private function formData(Request $request, User $user): array
    {
        return [
            'user' => $user,
            'roles' => $this->assignableRoles($request),
            'departments' => Department::orderBy('name')->pluck('name', 'id'),
            'designations' => array_combine(self::DESIGNATIONS, self::DESIGNATIONS),
        ];
    }

    private function validated(Request $request, ?User $user = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'min:3', 'max:120'],
            'email' => ['required', 'email:rfc', 'max:120', Rule::unique('users', 'email')->ignore($user?->id)],
            'role' => ['required', Rule::in(array_keys($this->assignableRoles($request)))],
            'designation' => ['required', 'string', 'max:80'],
            'department_id' => [Rule::requiredIf($request->input('role') === 'teacher'), 'nullable', 'exists:departments,id'],
            'employee_no' => ['required', 'string', 'max:32', 'regex:/^[A-Z0-9-]+$/', Rule::unique('users', 'employee_no')->ignore($user?->id)],
            'phone' => ['required', 'regex:/^(03\d{2}-\d{7}|0\d{2}-\d{7})$/'],
        ], [
            'role.in' => 'You are not allowed to assign this role.',
            'department_id.required' => 'Teachers must belong to a department.',
            'phone.regex' => 'Use 0300-1234567 (mobile) or 051-1234567 (landline).',
        ]);
    }

    private function nextEmployeeNo(): string
    {
        $max = User::where('employee_no', 'like', 'FGLA-%')->pluck('employee_no')
            ->map(fn ($n) => (int) Str::after($n, 'FGLA-'))->max() ?? 0;

        return sprintf('FGLA-%04d', $max + 1);
    }
}
