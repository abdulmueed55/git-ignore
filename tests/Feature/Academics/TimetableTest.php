<?php

namespace Tests\Feature\Academics;

class TimetableTest extends AcademicsTestCase
{
    public function test_section_clash_is_rejected_with_a_named_reason(): void
    {
        $physics = $this->course($this->section, $this->teacher, 'PHY-I');
        $maths = $this->course($this->section, $this->otherTeacher, 'MTH-I');
        $this->slot($physics, 1, '08:00', '08:45');

        $this->actingAs($this->principal)
            ->post(route('academics.timetable.slots.store'), ['course_id' => $maths->id, 'weekday' => 1, 'starts_at' => '08:30', 'ends_at' => '09:15', 'room' => 'A-1'])
            ->assertSessionHasErrors('starts_at');

        $this->assertStringContainsString('already has Subject PHY-I', session('errors')->first('starts_at'));
        $this->assertDatabaseCount('timetable_slots', 1);
    }

    public function test_teacher_clash_across_sections_is_rejected(): void
    {
        $a = $this->course($this->section, $this->teacher, 'PHY-I');
        $b = $this->course($this->otherSection, $this->teacher, 'PHY-II');
        $this->slot($a, 2, '10:30', '11:15');

        $this->actingAs($this->principal)
            ->post(route('academics.timetable.slots.store'), ['course_id' => $b->id, 'weekday' => 2, 'starts_at' => '10:30', 'ends_at' => '11:15', 'room' => 'A-2'])
            ->assertSessionHasErrors('starts_at');

        $this->assertStringContainsString('is already teaching', session('errors')->first('starts_at'));
    }

    public function test_back_to_back_periods_do_not_clash_and_moving_a_slot_ignores_itself(): void
    {
        $physics = $this->course($this->section, $this->teacher, 'PHY-I');
        $slot = $this->slot($physics, 3, '08:00', '08:45');

        $this->actingAs($this->principal)
            ->post(route('academics.timetable.slots.store'), ['course_id' => $physics->id, 'weekday' => 3, 'starts_at' => '08:45', 'ends_at' => '09:30', 'room' => 'A-1'])
            ->assertSessionHasNoErrors();

        $this->actingAs($this->principal)
            ->put(route('academics.timetable.slots.update', $slot), ['course_id' => $physics->id, 'weekday' => 3, 'starts_at' => '08:00', 'ends_at' => '08:45', 'room' => 'Lab 2'])
            ->assertSessionHasNoErrors();
        $this->assertSame('Lab 2', $slot->fresh()->room);
    }

    public function test_assigning_a_busy_teacher_is_rejected(): void
    {
        $mine = $this->course($this->otherSection, $this->otherTeacher, 'MTH-I');
        $this->slot($mine, 4, '12:00', '12:45');
        $target = $this->course($this->section, $this->teacher, 'PHY-I');
        $this->slot($target, 4, '12:00', '12:45');

        $this->actingAs($this->principal)
            ->put(route('academics.timetable.courses.teacher', $target), ['teacher_id' => $this->otherTeacher->id])
            ->assertSessionHasErrors('teacher_id');
        $this->assertSame($this->teacher->id, $target->fresh()->teacher_id);
    }

    public function test_adding_a_duplicate_course_is_rejected(): void
    {
        $c = $this->course($this->section, $this->teacher, 'PHY-I');

        $this->actingAs($this->principal)
            ->post(route('academics.timetable.courses.store'), ['section_id' => $this->section->id, 'subject_id' => $c->subject_id, 'teacher_id' => $this->teacher->id])
            ->assertSessionHasErrors('subject_id');
    }

    public function test_teacher_defaults_to_own_timetable_and_cannot_edit_or_view_others(): void
    {
        $c = $this->course($this->section, $this->teacher, 'PHY-I');
        $this->slot($c, 1, '08:00', '08:45');

        $this->actingAs($this->teacher)->get(route('academics.timetable'))
            ->assertOk()->assertSee('Subject PHY-I')->assertSee('Teaching timetable of');

        $this->actingAs($this->teacher)->get(route('academics.timetable', ['teacher' => $this->otherTeacher->id]))->assertForbidden();
        $this->actingAs($this->teacher)->get(route('academics.timetable', ['section' => $this->otherSection->id]))->assertForbidden();
        $this->actingAs($this->teacher)
            ->post(route('academics.timetable.slots.store'), ['course_id' => $c->id, 'weekday' => 2, 'starts_at' => '08:00', 'ends_at' => '08:45', 'room' => 'A-1'])
            ->assertForbidden();
    }

    public function test_leadership_pages_render(): void
    {
        $c = $this->course($this->section, $this->teacher, 'PHY-I');
        $this->slot($c, 1, '08:00', '08:45');
        $this->student($this->section);

        foreach ([
            route('academics.timetable', ['section' => $this->section->id]),
            route('academics.programs.index'), route('academics.programs.show', $this->program), route('academics.programs.create'),
            route('academics.sections.index'), route('academics.sections.show', $this->section), route('academics.sections.create'),
            route('academics.subjects.index'), route('academics.subjects.create'),
        ] as $url) {
            $this->actingAs($this->principal)->get($url)->assertOk();
        }
        $this->actingAs($this->teacher)->get(route('academics.programs.index'))->assertForbidden();
    }

    public function test_program_degree_weights_must_sum_to_one(): void
    {
        $payload = [
            'name' => 'BS Physics', 'code' => 'BSPHY', 'department_id' => $this->dept->id, 'level' => 'bs', 'duration_years' => 4, 'seats' => 50,
            'shift' => 'morning', 'eligibility' => 'Inter pre-engineering', 'min_percentage' => 50, 'annual_fee' => 40000,
            'weights' => ['academic' => 0.6, 'matric' => 0.3, 'hafiz_bonus' => 20],
        ];
        $this->actingAs($this->principal)->post(route('academics.programs.store'), $payload)->assertSessionHasErrors('weights.academic');

        $payload['weights']['matric'] = 0.4;
        $this->actingAs($this->principal)->post(route('academics.programs.store'), $payload)->assertSessionHasNoErrors();
        $this->assertDatabaseHas('programs', ['code' => 'BSPHY']);
    }
}
