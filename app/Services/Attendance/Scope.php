<?php

namespace App\Services\Attendance;

use App\Models\Course;
use App\Models\Student;
use App\Models\User;

/** What part of the college a user may see in the attendance module. */
class Scope
{
    /** null = everything (leadership); otherwise the course ids the teacher teaches. */
    public static function courseIds(User $user): ?array
    {
        if ($user->isLeadership()) {
            return null;
        }

        return Course::where('teacher_id', $user->id)->pluck('id')->all();
    }

    /** null = all sections. */
    public static function sectionIds(User $user): ?array
    {
        if ($user->isLeadership()) {
            return null;
        }

        return Course::where('teacher_id', $user->id)->distinct()->pluck('section_id')->all();
    }

    public static function canMark(User $user, Course $course): bool
    {
        return $user->isLeadership() || $course->teacher_id === $user->id;
    }

    public static function canViewStudent(User $user, Student $student): bool
    {
        return $user->isLeadership() || in_array($student->section_id, self::sectionIds($user), true);
    }
}
