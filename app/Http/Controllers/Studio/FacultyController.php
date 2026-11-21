<?php

namespace App\Http\Controllers\Studio;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Department;
use App\Models\FacultyProfile;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class FacultyController extends Controller
{
    public function index(Request $request): View
    {
        $dept = $request->query('department');
        $profiles = FacultyProfile::with('department')
            ->when($dept, fn ($q) => $q->where('department_id', $dept))
            ->orderBy('sort_order')->orderBy('name')->get();
        $importable = User::whereIn('role', ['teacher', 'principal'])->where('is_active', true)
            ->whereNotIn('id', FacultyProfile::whereNotNull('user_id')->pluck('user_id'))->count();

        return view('studio.faculty.index', [
            'profiles' => $profiles,
            'departments' => Department::orderBy('name')->get(),
            'department' => $dept,
            'importable' => $importable,
        ]);
    }

    public function create(): View
    {
        return view('studio.faculty.form', ['profile' => new FacultyProfile(['is_published' => true, 'sort_order' => 50]), 'departments' => Department::orderBy('name')->pluck('name', 'id')]);
    }

    public function store(Request $request): RedirectResponse
    {
        $p = new FacultyProfile;
        $this->fill($p, $request);

        return redirect()->route('studio.faculty.index')->with('success', "{$p->name} added to the faculty directory.");
    }

    public function edit(FacultyProfile $faculty): View
    {
        return view('studio.faculty.form', ['profile' => $faculty, 'departments' => Department::orderBy('name')->pluck('name', 'id')]);
    }

    public function update(Request $request, FacultyProfile $faculty): RedirectResponse
    {
        $this->fill($faculty, $request);

        return redirect()->route('studio.faculty.index')->with('success', 'Profile saved.');
    }

    public function destroy(FacultyProfile $faculty): RedirectResponse
    {
        if ($faculty->photo_path) {
            Storage::disk('public')->delete($faculty->photo_path);
        }
        $faculty->delete();

        return redirect()->route('studio.faculty.index')->with('success', 'Removed from the directory.');
    }

    /** Create draft profiles for staff accounts that don't have one yet. */
    public function import(): RedirectResponse
    {
        $users = User::whereIn('role', ['teacher', 'principal'])->where('is_active', true)
            ->whereNotIn('id', FacultyProfile::whereNotNull('user_id')->pluck('user_id'))->get();
        $order = (int) FacultyProfile::max('sort_order');
        foreach ($users as $u) {
            FacultyProfile::create([
                'user_id' => $u->id, 'department_id' => $u->department_id, 'name' => $u->name,
                'designation' => $u->designation ?: 'Lecturer', 'email' => null, // never publish staff sign-in emails
                'sort_order' => $u->role === 'principal' ? 0 : ++$order, 'is_published' => false,
            ]);
        }
        ActivityLog::record('studio.faculty.imported', "Imported {$users->count()} staff into the faculty directory");

        return back()->with('success', $users->count()
            ? "{$users->count()} staff imported as hidden drafts — add qualifications, then publish."
            : 'Everyone on staff already has a profile.');
    }

    private function fill(FacultyProfile $p, Request $request): void
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'min:3', 'max:120'],
            'designation' => ['required', 'string', 'max:80'],
            'department_id' => ['required', 'exists:departments,id'],
            'qualification' => ['required', 'string', 'max:160'],
            'specialization' => ['required', 'string', 'max:160'],
            'email' => ['nullable', 'email:rfc,strict,filter', 'max:160'],
            'bio' => ['nullable', 'string', 'max:1200'],
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:3072'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:999'],
            'is_published' => ['nullable', 'boolean'],
        ]);
        if ($request->hasFile('photo')) {
            if ($p->photo_path) {
                Storage::disk('public')->delete($p->photo_path);
            }
            $p->photo_path = $request->file('photo')->store('faculty', 'public');
        }
        unset($data['photo']);
        $p->fill([...$data, 'is_published' => $request->boolean('is_published')])->save();
    }
}
