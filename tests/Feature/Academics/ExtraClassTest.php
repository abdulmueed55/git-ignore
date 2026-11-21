<?php

namespace Tests\Feature\Academics;

use App\Models\ExtraClass;
use App\Models\GuardianAlert;
use App\Models\Student;
use App\Services\Timetable\ClassReminders;
use Carbon\Carbon;

class ExtraClassTest extends AcademicsTestCase
{
    private function payload(array $over = []): array
    {
        return $over + ['held_on' => Carbon::parse('next monday')->toDateString(), 'starts_at' => '13:45', 'ends_at' => '14:30', 'room' => 'B-2', 'kind' => 'extra', 'reason' => 'Revision'];
    }

    public function test_teacher_schedules_an_extra_class_for_own_subject_and_students_are_told(): void
    {
        $course = $this->course($this->section, $this->teacher);
        Student::create(['program_id' => $this->program->id, 'section_id' => $this->section->id, 'academic_session_id' => $this->session->id,
            'roll_no' => 'FSC-PE-26-001', 'name' => 'Ali Raza', 'father_name' => 'Raza Khan', 'phone' => '0300-1111111', 'status' => 'active']);

        $this->actingAs($this->teacher)->get(route('academics.extra.index'))->assertOk()->assertSee('Schedule a class');
        $this->actingAs($this->teacher)->post(route('academics.extra.store'), $this->payload(['course_id' => $course->id, 'notify_students' => '1']))
            ->assertRedirect(route('academics.extra.index'))->assertSessionHas('success');

        $e = ExtraClass::sole();
        $this->assertSame('13:45', $e->starts_at);
        $this->assertTrue($e->students_notified);
        $this->assertSame(1, GuardianAlert::where('kind', 'extra_class')->count());
        $this->assertDatabaseHas('activity_logs', ['action' => 'timetable.extra_scheduled']);
    }

    public function test_teacher_cannot_schedule_for_another_teachers_subject_or_in_the_past(): void
    {
        $theirs = $this->course($this->section, $this->otherTeacher, 'MTH-I');
        $mine = $this->course($this->section, $this->teacher);

        $this->actingAs($this->teacher)->post(route('academics.extra.store'), $this->payload(['course_id' => $theirs->id]))->assertSessionHasErrors('course_id');
        $this->actingAs($this->teacher)->post(route('academics.extra.store'), $this->payload(['course_id' => $mine->id, 'held_on' => today()->subDay()->toDateString()]))->assertSessionHasErrors('held_on');
        $this->actingAs($this->teacher)->post(route('academics.extra.store'), $this->payload(['course_id' => $mine->id, 'ends_at' => '13:00']))->assertSessionHasErrors('ends_at');
        $this->assertSame(0, ExtraClass::count());
    }

    public function test_clashes_with_the_timetable_or_another_extra_class_are_refused(): void
    {
        $mine = $this->course($this->section, $this->teacher);
        $monday = Carbon::parse('next monday');
        $this->slot($this->course($this->otherSection, $this->teacher, 'PHY-II'), 1, '10:30', '11:15');   // teacher busy (other section)
        $this->slot($this->course($this->section, $this->otherTeacher, 'MTH-I'), 1, '12:00', '12:45');   // class busy (other subject)

        $this->actingAs($this->teacher)->post(route('academics.extra.store'), $this->payload(['course_id' => $mine->id, 'held_on' => $monday->toDateString(), 'starts_at' => '10:45', 'ends_at' => '11:30']))
            ->assertSessionHasErrors(['starts_at' => 'The teacher already has Subject PHY-II 10:30–11:15 at that time.']);
        $this->actingAs($this->teacher)->post(route('academics.extra.store'), $this->payload(['course_id' => $mine->id, 'held_on' => $monday->toDateString(), 'starts_at' => '12:15', 'ends_at' => '13:00']))
            ->assertSessionHasErrors(['starts_at' => 'This class already has Subject MTH-I 12:00–12:45 at that time.']);
        // Back-to-back is fine.
        $this->actingAs($this->teacher)->post(route('academics.extra.store'), $this->payload(['course_id' => $mine->id, 'held_on' => $monday->toDateString(), 'starts_at' => '12:45', 'ends_at' => '13:30']))
            ->assertSessionHasNoErrors();
    }

    public function test_principal_schedules_for_a_teacher_who_is_alerted_and_can_cancel_it(): void
    {
        $course = $this->course($this->section, $this->teacher);
        $this->actingAs($this->principal)->post(route('academics.extra.store'), $this->payload(['course_id' => $course->id, 'kind' => 'test']))->assertSessionHas('success');

        $this->assertSame(1, $this->teacher->unreadNotifications()->count());
        $feed = $this->actingAs($this->teacher)->getJson(route('notifications.feed'))->assertOk();
        $feed->assertJsonPath('unread', 1)->assertJsonPath('items.0.title', 'Class test scheduled: Subject PHY-I');

        // Another teacher cannot cancel it; its own teacher can.
        $e = ExtraClass::sole();
        $this->actingAs($this->otherTeacher)->patch(route('academics.extra.cancel', $e))->assertForbidden();
        $this->actingAs($this->teacher)->patch(route('academics.extra.cancel', $e))->assertSessionHas('success');
        $this->assertNotNull($e->fresh()->cancelled_at);

        $this->actingAs($this->teacher)->postJson(route('notifications.read'), [])->assertJsonPath('unread', 0);
    }

    public function test_teacher_is_alerted_before_each_class_once_including_extra_classes(): void
    {
        $monday = Carbon::parse('next monday');
        $course = $this->course($this->section, $this->teacher);
        $this->slot($course, 1, '10:30', '11:15');
        ExtraClass::create(['course_id' => $course->id, 'held_on' => $monday->toDateString(), 'starts_at' => '13:45', 'ends_at' => '14:30', 'room' => 'Lab', 'kind' => 'makeup', 'created_by' => $this->teacher->id]);
        $r = new ClassReminders;

        // 07:30 — the day's list.
        $this->assertSame(1, $r->run($monday->copy()->setTime(7, 30)));
        $daily = $this->teacher->notifications()->sole();
        $this->assertSame('Today: 2 classes (1 extra)', $daily->data['title']);

        // 10:21 — the 10:30 period is 9 minutes away; asking again sends nothing new.
        $this->assertSame(1, $r->run($monday->copy()->setTime(10, 21)));
        $this->assertSame(0, $r->run($monday->copy()->setTime(10, 25)));
        $soon = fn () => $this->teacher->notifications()->get()->filter(fn ($n) => $n->data['kind'] === 'class_soon')->pluck('data.title')->values()->all();
        $this->assertSame(['Subject PHY-I in 9 min'], $soon());

        // 13:40 — the make-up class.
        $this->assertSame(1, $r->run($monday->copy()->setTime(13, 40)));
        $this->assertContains('Make-up class: Subject PHY-I in 5 min', $soon());
        $this->assertSame(3, $this->teacher->notifications()->count());
    }

    public function test_extra_class_opens_its_own_register_and_shows_on_the_day_view(): void
    {
        Carbon::setTestNow(Carbon::parse('next monday')->setTime(13, 50));
        $course = $this->course($this->section, $this->teacher);
        $this->slot($course, 1, '10:30', '11:15');
        $e = ExtraClass::create(['course_id' => $course->id, 'held_on' => today()->toDateString(), 'starts_at' => '13:45', 'ends_at' => '14:30', 'room' => 'Lab', 'kind' => 'extra', 'created_by' => $this->teacher->id]);

        $this->actingAs($this->teacher)->get(route('attendance.index'))->assertOk()->assertSee('Extra class')->assertSee('13:45');
        $this->actingAs($this->teacher)->get(route('attendance.mark', ['course' => $course->id, 'date' => today()->toDateString(), 'time' => '13:45', 'extra' => $e->id]))
            ->assertOk()->assertSee('Extra class')->assertSee('name="time" value="13:45"', false)->assertDontSee('name="slot"', false);
        Carbon::setTestNow();
    }
}
