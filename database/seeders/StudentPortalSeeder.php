<?php

namespace Database\Seeders;

use App\Models\Course;
use App\Models\LeaveRequest;
use App\Models\Student;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Demo student-portal accounts. One featured student (in the demo teacher's class, so
 * today's extra class shows) signs in with password "password"; the rest have the same
 * demo password but must choose their own at first sign-in, like real new students.
 */
class StudentPortalSeeder extends Seeder
{
    public function run(): void
    {
        $hash = Hash::make('password');
        Student::query()->update(['password' => $hash, 'must_change_password' => true]);

        $teacher = User::where('email', 'teacher@fglacollege.edu.pk')->first();
        $sectionId = $teacher ? Course::where('teacher_id', $teacher->id)->orderBy('id')->value('section_id') : null;
        $featured = Student::where('status', 'active')->when($sectionId, fn ($q) => $q->where('section_id', $sectionId))->orderBy('roll_no')->first();
        if (! $featured) {
            return;
        }
        // Keep the featured student first by id so the sign-in page can show it as the demo account.
        Student::whereKey($featured->id)->update(['must_change_password' => false, 'portal_last_login_at' => now()->subDay()]);
        Student::where('status', 'active')->where('id', '<', $featured->id)->whereNotNull('password')->update(['must_change_password' => true]);

        LeaveRequest::create([
            'student_id' => $featured->id, 'source' => 'student', 'status' => 'pending',
            'from_date' => today()->addDays(3)->toDateString(), 'to_date' => today()->addDays(4)->toDateString(),
            'reason' => 'Cousin’s wedding in Jhelum — family travelling for two days',
        ]);
    }
}
