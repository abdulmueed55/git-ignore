<?php

namespace Tests\Feature\Academics;

use App\Models\ActivityLog;
use App\Models\User;

class StaffTest extends AcademicsTestCase
{
    private function payload(array $o = []): array
    {
        return array_merge(['name' => 'Mr. New Lecturer', 'email' => 'new@fglacollege.edu.pk', 'role' => 'teacher', 'designation' => 'Lecturer',
            'department_id' => $this->dept->id, 'employee_no' => 'FGLA-0999', 'phone' => '0300-1234567'], $o);
    }

    public function test_principal_creates_staff_and_sees_temporary_password_once(): void
    {
        $this->actingAs($this->principal)->post(route('staff.store'), $this->payload())
            ->assertRedirect(route('staff.index'))->assertSessionHas('credentials');

        $user = User::where('email', 'new@fglacollege.edu.pk')->first();
        $this->assertNotNull($user);
        $this->assertTrue($user->is_active);
    }

    public function test_principal_cannot_create_or_edit_admins(): void
    {
        $this->actingAs($this->principal)->post(route('staff.store'), $this->payload(['role' => 'admin']))->assertSessionHasErrors('role');

        $admin = $this->user('admin');
        $this->actingAs($this->principal)->get(route('staff.edit', $admin))->assertForbidden();
        $this->actingAs($this->principal)->patch(route('staff.toggle', $admin))->assertForbidden();
        $this->actingAs($this->principal)->post(route('staff.reset-password', $admin))->assertForbidden();
    }

    public function test_admin_can_create_admins(): void
    {
        $this->actingAs($this->user('admin'))->post(route('staff.store'), $this->payload(['role' => 'admin', 'department_id' => null]))
            ->assertSessionHasNoErrors();
        $this->assertDatabaseHas('users', ['email' => 'new@fglacollege.edu.pk', 'role' => 'admin']);
    }

    public function test_teachers_need_a_department_and_valid_phone(): void
    {
        $this->actingAs($this->principal)->post(route('staff.store'), $this->payload(['department_id' => null, 'phone' => '12345']))
            ->assertSessionHasErrors(['department_id', 'phone']);
    }

    public function test_toggle_and_reset_password(): void
    {
        $this->actingAs($this->principal)->patch(route('staff.toggle', $this->teacher))->assertRedirect();
        $this->assertFalse($this->teacher->fresh()->is_active);

        $old = $this->teacher->fresh()->password;
        $this->actingAs($this->principal)->post(route('staff.reset-password', $this->teacher))->assertSessionHas('credentials');
        $this->assertNotSame($old, $this->teacher->fresh()->password);

        $this->actingAs($this->principal)->patch(route('staff.toggle', $this->principal))->assertSessionHas('error');
        $this->assertTrue($this->principal->fresh()->is_active);
    }

    public function test_non_leadership_cannot_manage_staff(): void
    {
        foreach (['teacher', 'admission_officer', 'editor'] as $role) {
            $this->actingAs($this->user($role))->get(route('staff.index'))->assertForbidden();
        }
    }

    public function test_activity_log_is_restricted_and_filterable(): void
    {
        $this->actingAs($this->principal);
        ActivityLog::record('students.created', 'Enrolled Ali Raza');
        ActivityLog::record('studio.post_published', 'Published Annual sports gala');

        $this->get(route('system.activity'))->assertOk()->assertSee('enrolled Ali Raza')->assertSee('published Annual sports gala');
        $this->get(route('system.activity', ['module' => 'students']))->assertOk()->assertSee('enrolled Ali Raza')->assertDontSee('Annual sports gala');

        $this->actingAs($this->teacher)->get(route('system.activity'))->assertForbidden();
        $this->actingAs($this->user('admission_officer'))->get(route('system.activity'))->assertForbidden();
    }
}
