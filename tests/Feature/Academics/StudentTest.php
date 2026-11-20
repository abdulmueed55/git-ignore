<?php

namespace Tests\Feature\Academics;

use App\Models\Program;
use App\Models\Section;
use App\Models\Student;

class StudentTest extends AcademicsTestCase
{
    public function test_principal_can_enrol_a_student_with_valid_data(): void
    {
        $this->actingAs($this->principal)
            ->post(route('students.store'), $this->validStudentPayload())
            ->assertRedirect()->assertSessionHasNoErrors();

        $this->assertDatabaseHas('students', ['roll_no' => 'FSC-PE-26-099', 'cnic' => '37405-1234567-1']);
        $this->assertDatabaseHas('activity_logs', ['action' => 'students.created']);
    }

    public function test_cnic_and_phone_formats_are_enforced(): void
    {
        $this->actingAs($this->principal)
            ->post(route('students.store'), $this->validStudentPayload(['cnic' => '3740512345671', 'phone' => '+92 300 1234567', 'guardian_phone' => '0300-12345']))
            ->assertSessionHasErrors(['cnic', 'phone', 'guardian_phone']);

        $this->assertDatabaseCount('students', 0);
    }

    public function test_every_field_is_required(): void
    {
        $this->actingAs($this->principal)->post(route('students.store'), [])
            ->assertSessionHasErrors(['name', 'father_name', 'roll_no', 'registration_no', 'cnic', 'father_cnic', 'date_of_birth', 'gender', 'blood_group', 'phone', 'guardian_phone', 'email', 'address', 'city', 'domicile', 'program_id', 'section_id', 'academic_session_id', 'status']);
    }

    public function test_roll_number_must_be_unique_but_can_be_kept_on_update(): void
    {
        $existing = $this->student($this->section);

        $this->actingAs($this->principal)
            ->post(route('students.store'), $this->validStudentPayload(['roll_no' => $existing->roll_no]))
            ->assertSessionHasErrors('roll_no');

        $this->actingAs($this->principal)
            ->put(route('students.update', $existing), $this->validStudentPayload(['roll_no' => $existing->roll_no, 'cnic' => $existing->cnic, 'registration_no' => $existing->registration_no, 'name' => 'Renamed Student']))
            ->assertSessionHasNoErrors();
        $this->assertSame('Renamed Student', $existing->fresh()->name);
    }

    public function test_section_must_belong_to_the_selected_program(): void
    {
        $otherProgram = Program::create(['name' => 'I.Com.', 'code' => 'ICOM', 'level' => 'intermediate']);
        $foreign = Section::create(['program_id' => $otherProgram->id, 'academic_session_id' => $this->session->id, 'name' => 'Part-I · A']);

        $this->actingAs($this->principal)
            ->post(route('students.store'), $this->validStudentPayload(['section_id' => $foreign->id]))
            ->assertSessionHasErrors('section_id');
    }

    public function test_teacher_sees_only_students_of_sections_they_teach(): void
    {
        $this->course($this->section, $this->teacher);
        $mine = $this->student($this->section, ['name' => 'Mine Student']);
        $theirs = $this->student($this->otherSection, ['name' => 'Other Student']);

        $this->actingAs($this->teacher)->get(route('students.index'))
            ->assertOk()->assertSee('Mine Student')->assertDontSee('Other Student');

        $this->actingAs($this->teacher)->get(route('students.show', $mine))->assertOk();
        $this->actingAs($this->teacher)->get(route('students.show', $theirs))->assertForbidden();
    }

    public function test_teacher_is_read_only(): void
    {
        $this->course($this->section, $this->teacher);
        $student = $this->student($this->section);

        $this->actingAs($this->teacher)->get(route('students.create'))->assertForbidden();
        $this->actingAs($this->teacher)->post(route('students.store'), $this->validStudentPayload())->assertForbidden();
        $this->actingAs($this->teacher)->put(route('students.update', $student), $this->validStudentPayload())->assertForbidden();
        $this->actingAs($this->teacher)->get(route('students.show', $student))->assertOk()->assertDontSee('Edit record');
    }

    public function test_editor_cannot_access_students(): void
    {
        $this->actingAs($this->user('editor'))->get(route('students.index'))->assertForbidden();
    }

    public function test_index_search_profile_card_and_export_render(): void
    {
        $s = $this->student($this->section, ['name' => 'Zainab Ali', 'gender' => 'female']);
        $this->student($this->section, ['name' => 'Bilal Hussain']);

        $this->actingAs($this->principal)->get(route('students.index', ['q' => 'Zainab']))
            ->assertOk()->assertSee('Zainab Ali')->assertDontSee('Bilal Hussain');
        $this->actingAs($this->principal)->get(route('students.show', $s))->assertOk()->assertSee('No lectures recorded yet');
        $this->actingAs($this->principal)->get(route('students.card', $s))->assertOk()->assertSee($s->roll_no);

        $csv = $this->actingAs($this->principal)->get(route('students.export', ['q' => 'Zainab']));
        $csv->assertOk();
        $this->assertStringContainsString('Zainab Ali', $csv->streamedContent());
        $this->assertStringNotContainsString('Bilal Hussain', $csv->streamedContent());
    }
}
