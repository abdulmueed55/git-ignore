<?php

namespace Tests\Feature\Student;

use App\Models\AdmissionApplication;
use App\Models\LeaveRequest;
use App\Models\Student;
use App\Models\User;
use App\Services\Admissions\Admitter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\Feature\Admissions\AdmissionTestHelpers;
use Tests\TestCase;

class StudentPortalTest extends TestCase
{
    use AdmissionTestHelpers, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpAdmissions();
    }

    /** Admit an applicant whose PIN is 482913; returns the new student. */
    private function admitted(): Student
    {
        $app = $this->application(['status' => 'verified', 'access_pin' => '482913']);
        $this->actingAs($this->staff('admission_officer'));

        return (new Admitter)->admit($app);
    }

    public function test_admission_opens_the_portal_with_roll_number_and_pin_then_forces_a_new_password(): void
    {
        $s = $this->admitted();
        auth()->logout();
        $this->assertTrue($s->fresh()->must_change_password);

        $this->post(route('student.login.store'), ['roll_no' => strtolower($s->roll_no), 'password' => '482913'])->assertRedirect(route('student.home'));
        $this->assertAuthenticatedAs($s->fresh(), 'student');
        $this->get(route('student.home'))->assertRedirect(route('student.password.edit'));

        $this->put(route('student.password.update'), ['current_password' => '482913', 'password' => 'short', 'password_confirmation' => 'short'])->assertSessionHasErrors('password');
        $this->put(route('student.password.update'), ['current_password' => '482913', 'password' => 'Lahore2026x', 'password_confirmation' => 'Lahore2026x'])->assertRedirect(route('student.home'));
        $this->assertTrue(Hash::check('Lahore2026x', $s->fresh()->password));

        $this->get(route('student.home'))->assertOk()->assertSee($s->roll_no)->assertSee('Today’s classes', false);
        $this->get(route('student.application'))->assertOk()->assertSee($s->application->tracking_no)->assertSee('Admitted');
        $this->get(route('student.attendance'))->assertOk();
        $this->get(route('student.timetable'))->assertOk();
    }

    public function test_applicant_status_page_shows_roll_number_and_portal_after_admission(): void
    {
        $s = $this->admitted();
        auth()->logout();
        $app = AdmissionApplication::find($s->admission_application_id);

        $this->post(route('apply.status.lookup'), ['tracking_no' => $app->tracking_no, 'pin' => '482913']);
        $this->get(route('apply.status', ['tracking_no' => $app->tracking_no]))->assertOk()
            ->assertSee($s->roll_no)->assertSee('Your student portal is ready')->assertSee('Your application');
    }

    public function test_wrong_passwords_lock_the_roll_number_and_students_cannot_open_staff_pages(): void
    {
        $s = $this->admitted();
        auth()->logout();
        foreach (range(1, 10) as $i) {
            $this->withServerVariables(['REMOTE_ADDR' => "10.2.0.{$i}"])->post(route('student.login.store'), ['roll_no' => $s->roll_no, 'password' => '000000'])->assertSessionHasErrors('roll_no');
        }
        $this->withServerVariables(['REMOTE_ADDR' => '10.2.0.99'])->post(route('student.login.store'), ['roll_no' => $s->roll_no, 'password' => '482913'])->assertSessionHasErrors('roll_no');
        $this->assertGuest('student');

        // Signed in as a student, staff pages still ask for a staff login.
        $this->actingAs($s->fresh(), 'student');
        $this->app['auth']->shouldUse('web'); // as in a real request: staff pages use the staff guard
        $this->get(route('dashboard'))->assertRedirect(route('login'));
        $this->get(route('students.index'))->assertRedirect(route('login'));
    }

    public function test_student_asks_for_leave_and_the_principal_is_alerted(): void
    {
        $s = $this->admitted();
        auth()->logout();
        $principal = $this->staff('principal');
        $s->forceFill(['must_change_password' => false])->save();

        $this->actingAs($s, 'student')->post(route('student.leave.store'), ['from_date' => today()->toDateString(), 'to_date' => today()->addDays(20)->toDateString(), 'reason' => 'Family wedding in Jhelum'])
            ->assertSessionHasErrors('to_date');
        $this->actingAs($s, 'student')->post(route('student.leave.store'), ['from_date' => today()->toDateString(), 'to_date' => today()->addDay()->toDateString(), 'reason' => 'Fever, will bring certificate'])
            ->assertRedirect(route('student.leave'));

        $leave = LeaveRequest::sole();
        $this->assertSame(['student', 'pending', $s->id], [$leave->source, $leave->status, $leave->student_id]);
        $this->assertSame(1, $principal->unreadNotifications()->count());
        $this->actingAs($s, 'student')->get(route('student.leave'))->assertOk()->assertSee('Fever, will bring certificate');
    }

    public function test_office_can_issue_a_temporary_password_but_a_teacher_cannot(): void
    {
        $s = $this->admitted();
        $teacher = User::create(['name' => 'T', 'email' => 't@test.pk', 'password' => 'password', 'role' => 'teacher', 'is_active' => true]);

        $this->actingAs($teacher)->post(route('students.portal-password', $s))->assertForbidden();
        $this->actingAs($this->staff('principal'))->post(route('students.portal-password', $s))->assertSessionHas('portal_password');
        $temp = session('portal_password');
        $this->assertTrue(Hash::check($temp, $s->fresh()->password));
        $this->assertTrue($s->fresh()->must_change_password);
    }

    public function test_inactive_students_are_signed_out(): void
    {
        $s = $this->admitted();
        auth()->logout();
        $s->forceFill(['must_change_password' => false, 'status' => 'struck_off'])->save();

        $this->actingAs($s, 'student')->get(route('student.home'))->assertRedirect(route('student.login'));
        $this->assertGuest('student');
    }
}
