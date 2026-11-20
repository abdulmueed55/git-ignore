<?php

namespace Tests\Feature\Academics;

use App\Models\AcademicSession;
use App\Models\Course;
use App\Models\Department;
use App\Models\Program;
use App\Models\Section;
use App\Models\Student;
use App\Models\Subject;
use App\Models\TimetableSlot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

abstract class AcademicsTestCase extends TestCase
{
    use RefreshDatabase;

    protected Department $dept;

    protected AcademicSession $session;

    protected Program $program;

    protected Section $section;

    protected Section $otherSection;

    protected User $principal;

    protected User $teacher;

    protected User $otherTeacher;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();

        $this->dept = Department::create(['name' => 'Physics', 'code' => 'PHY']);
        $this->session = AcademicSession::create(['name' => '2026–2028', 'starts_on' => '2026-09-01', 'ends_on' => '2028-06-30', 'is_current' => true]);
        $this->program = Program::create(['name' => 'F.Sc. Pre-Engineering', 'code' => 'FSC-PE', 'level' => 'intermediate', 'department_id' => $this->dept->id]);

        $this->principal = $this->user('principal');
        $this->teacher = $this->user('teacher', 'teacher@test.pk');
        $this->otherTeacher = $this->user('teacher', 'other@test.pk');

        $this->section = Section::create(['program_id' => $this->program->id, 'academic_session_id' => $this->session->id, 'name' => 'Part-I · A', 'room' => 'A-1', 'capacity' => 40]);
        $this->otherSection = Section::create(['program_id' => $this->program->id, 'academic_session_id' => $this->session->id, 'name' => 'Part-I · B', 'room' => 'A-2', 'capacity' => 40]);
    }

    protected function user(string $role, ?string $email = null): User
    {
        return User::create([
            'name' => ucfirst($role).' '.uniqid(), 'email' => $email ?? $role.uniqid().'@test.pk', 'password' => 'password',
            'role' => $role, 'is_active' => true, 'department_id' => $this->dept->id, 'employee_no' => 'E-'.strtoupper(uniqid()),
        ]);
    }

    protected function course(Section $section, ?User $teacher = null, string $code = 'PHY-I'): Course
    {
        $subject = Subject::firstOrCreate(['code' => $code], ['name' => 'Subject '.$code, 'department_id' => $this->dept->id]);

        return Course::create(['section_id' => $section->id, 'subject_id' => $subject->id, 'teacher_id' => $teacher?->id]);
    }

    protected function slot(Course $course, int $day, string $start, string $end): TimetableSlot
    {
        return TimetableSlot::create(['course_id' => $course->id, 'weekday' => $day, 'starts_at' => $start, 'ends_at' => $end, 'room' => 'A-1']);
    }

    protected function student(Section $section, array $attrs = []): Student
    {
        static $n = 0;
        $n++;

        return Student::create($attrs + [
            'program_id' => $section->program_id, 'section_id' => $section->id, 'academic_session_id' => $section->academic_session_id,
            'roll_no' => 'FSC-PE-26-'.str_pad($n, 3, '0', STR_PAD_LEFT), 'registration_no' => 'REG-'.$n,
            'name' => 'Student '.$n, 'father_name' => 'Father '.$n, 'cnic' => sprintf('37405-%07d-1', $n), 'gender' => 'male', 'status' => 'active',
        ]);
    }

    protected function validStudentPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Hamza Khan', 'father_name' => 'Imran Khan', 'roll_no' => 'FSC-PE-26-099', 'registration_no' => 'BISE-RWP-26123456',
            'cnic' => '37405-1234567-1', 'father_cnic' => '37405-7654321-3', 'date_of_birth' => '2010-03-15', 'gender' => 'male',
            'blood_group' => 'B+', 'phone' => '0300-1234567', 'guardian_phone' => '0333-7654321', 'email' => 'hamza@example.com',
            'address' => 'House 12, Street 4, Westridge', 'city' => 'Rawalpindi', 'domicile' => 'Rawalpindi',
            'program_id' => $this->program->id, 'section_id' => $this->section->id, 'academic_session_id' => $this->session->id, 'status' => 'active',
        ], $overrides);
    }
}
