<?php

namespace Tests\Feature\Attendance;

use App\Models\AcademicSession;
use App\Models\AttendanceRecord;
use App\Models\Course;
use App\Models\GuardianAlert;
use App\Models\LeaveRequest;
use App\Models\Lecture;
use App\Models\Program;
use App\Models\Section;
use App\Models\Student;
use App\Models\Subject;
use App\Models\TimetableSlot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;

class AttendanceTest extends TestCase
{
    use RefreshDatabase;

    private User $teacher;

    private User $otherTeacher;

    private User $principal;

    private Course $course;

    private Course $otherCourse;

    /** @var Collection<int, Student> */
    private $students;

    protected function setUp(): void
    {
        parent::setUp();

        $this->principal = User::factory()->create(['role' => 'principal', 'is_active' => true]);
        $this->teacher = User::factory()->create(['role' => 'teacher', 'is_active' => true]);
        $this->otherTeacher = User::factory()->create(['role' => 'teacher', 'is_active' => true]);

        $session = AcademicSession::create(['name' => '2026–2028', 'starts_on' => now()->subYear()->toDateString(), 'ends_on' => now()->addYear()->toDateString(), 'is_current' => true]);
        $program = Program::create(['name' => 'F.Sc. Pre-Engineering', 'code' => 'FSC-PE', 'level' => 'intermediate']);
        $section = Section::create(['program_id' => $program->id, 'academic_session_id' => $session->id, 'name' => 'Part-I · A', 'room' => 'A-1']);
        $physics = Subject::create(['name' => 'Physics', 'code' => 'PHY-I']);
        $maths = Subject::create(['name' => 'Mathematics', 'code' => 'MTH-I']);

        $this->course = Course::create(['section_id' => $section->id, 'subject_id' => $physics->id, 'teacher_id' => $this->teacher->id]);
        $this->otherCourse = Course::create(['section_id' => $section->id, 'subject_id' => $maths->id, 'teacher_id' => $this->otherTeacher->id]);

        $this->students = collect(range(1, 3))->map(fn ($i) => Student::create([
            'program_id' => $program->id, 'section_id' => $section->id, 'academic_session_id' => $session->id,
            'roll_no' => "FSC-PE-26-00$i", 'name' => "Student $i", 'father_name' => "Father $i",
            'guardian_phone' => '0300-12345'.$i.'0', 'status' => 'active',
        ]));
    }

    private function statuses(string ...$s): array
    {
        return $this->students->values()->mapWithKeys(fn ($st, $i) => [$st->id => $s[$i]])->all();
    }

    /** Create a historical lecture for the course with the given statuses. */
    private function lecture(string $date, array $statuses, ?Course $course = null, string $time = '08:00'): Lecture
    {
        $lecture = Lecture::create(['course_id' => ($course ?? $this->course)->id, 'held_on' => $date, 'starts_at' => $time, 'topic' => 'Vectors', 'marked_by' => $this->teacher->id, 'locked_at' => now()->subDay()]);
        foreach ($statuses as $sid => $st) {
            AttendanceRecord::create(['lecture_id' => $lecture->id, 'student_id' => $sid, 'status' => $st]);
        }
        $lecture->refreshCounts();

        return $lecture;
    }

    public function test_teacher_can_open_own_register_but_not_another_teachers(): void
    {
        $this->actingAs($this->teacher)
            ->get(route('attendance.mark', ['course' => $this->course, 'time' => '08:00']))
            ->assertOk()->assertSee('Student 1')->assertSee('Topic covered');

        $this->actingAs($this->teacher)
            ->get(route('attendance.mark', ['course' => $this->otherCourse, 'time' => '08:00']))
            ->assertForbidden();

        $this->actingAs($this->teacher)
            ->post(route('attendance.save', $this->otherCourse), ['date' => today()->toDateString(), 'time' => '08:00', 'topic' => 'Limits', 'status' => $this->statuses('P', 'P', 'P')])
            ->assertForbidden();
    }

    public function test_saving_a_register_creates_records_counts_and_guardian_alert(): void
    {
        $this->actingAs($this->teacher)
            ->post(route('attendance.save', $this->course), [
                'date' => today()->toDateString(), 'time' => '08:00', 'topic' => 'Projectile motion',
                'status' => $this->statuses('P', 'A', 'L'), 'remarks' => [$this->students[2]->id => 'came after roll call'],
            ])
            ->assertRedirect(route('attendance.index', ['date' => today()->toDateString()]))
            ->assertSessionHas('success');

        $lecture = Lecture::sole();
        $this->assertSame(1, $lecture->present_count);
        $this->assertSame(1, $lecture->absent_count);
        $this->assertSame(1, $lecture->late_count);
        $this->assertSame(today()->toDateString(), $lecture->held_on->toDateString());
        $this->assertNotNull($lecture->locked_at);
        $this->assertSame(3, AttendanceRecord::count());
        $this->assertSame('came after roll call', AttendanceRecord::where('student_id', $this->students[2]->id)->value('remarks'));

        $absent = $this->students[1];
        $this->assertTrue(GuardianAlert::where('student_id', $absent->id)->where('kind', 'absent_today')->where('status', 'queued')->exists());
        $this->assertStringContainsString('ghair-haazir', GuardianAlert::where('kind', 'absent_today')->value('message'));
        $this->assertFalse(GuardianAlert::where('student_id', $this->students[0]->id)->exists());

        // Re-saving the same period updates, not duplicates, and does not re-alert
        $this->actingAs($this->teacher)->post(route('attendance.save', $this->course), [
            'date' => today()->toDateString(), 'time' => '08:00', 'topic' => 'Projectile motion', 'status' => $this->statuses('P', 'A', 'P'),
        ]);
        $this->assertSame(1, Lecture::count());
        $this->assertSame(2, Lecture::sole()->present_count);
        $this->assertSame(1, GuardianAlert::where('kind', 'absent_today')->count());
    }

    public function test_every_student_must_be_marked_and_topic_is_required(): void
    {
        $partial = $this->statuses('P', 'P', 'P');
        array_pop($partial);

        $this->actingAs($this->teacher)
            ->post(route('attendance.save', $this->course), ['date' => today()->toDateString(), 'time' => '08:00', 'topic' => '', 'status' => $partial])
            ->assertSessionHasErrors(['topic']);

        $this->actingAs($this->teacher)
            ->post(route('attendance.save', $this->course), ['date' => today()->toDateString(), 'time' => '08:00', 'topic' => 'Vectors', 'status' => $partial])
            ->assertSessionHasErrors(['status']);

        $this->actingAs($this->teacher)
            ->post(route('attendance.save', $this->course), ['date' => today()->addDay()->toDateString(), 'time' => '08:00', 'topic' => 'Vectors', 'status' => $this->statuses('P', 'P', 'P')])
            ->assertSessionHasErrors(['date']);

        $this->assertSame(0, Lecture::count());
    }

    public function test_locked_register_cannot_be_changed_by_teacher_but_can_by_principal(): void
    {
        $date = today()->subDays(5)->toDateString();
        $lecture = $this->lecture($date, $this->statuses('P', 'P', 'P'));

        $this->actingAs($this->teacher)
            ->post(route('attendance.save', $this->course), ['date' => $date, 'time' => '08:00', 'topic' => 'Vectors', 'status' => $this->statuses('A', 'A', 'A')])
            ->assertSessionHas('error');
        $this->assertSame(3, $lecture->fresh()->present_count);

        $this->actingAs($this->teacher)->get(route('attendance.mark', ['course' => $this->course, 'date' => $date, 'time' => '08:00']))
            ->assertOk()->assertSee('Only the principal’s office can change it now', false);

        $this->actingAs($this->principal)
            ->post(route('attendance.save', $this->course), ['date' => $date, 'time' => '08:00', 'topic' => 'Vectors (corrected)', 'status' => $this->statuses('P', 'A', 'P')])
            ->assertSessionHas('success');
        $this->assertSame(1, $lecture->fresh()->absent_count);
        $this->assertSame('Vectors (corrected)', $lecture->fresh()->topic);
    }

    public function test_approving_leave_converts_absences_to_leave(): void
    {
        $s = $this->students[0];
        $day = today()->subDays(3)->toDateString();
        $l1 = $this->lecture($day, $this->statuses('A', 'P', 'P'));
        $l2 = $this->lecture(today()->subDays(10)->toDateString(), $this->statuses('A', 'P', 'P'));

        $leave = LeaveRequest::create(['student_id' => $s->id, 'from_date' => today()->subDays(4)->toDateString(), 'to_date' => $day, 'reason' => 'Fever, medical certificate', 'status' => 'pending']);

        $this->actingAs($this->teacher)->patch(route('attendance.leaves.approve', $leave))->assertSessionHas('success');

        $this->assertSame('approved', $leave->fresh()->status);
        $this->assertSame('E', AttendanceRecord::where('lecture_id', $l1->id)->where('student_id', $s->id)->value('status'));
        $this->assertSame('A', AttendanceRecord::where('lecture_id', $l2->id)->where('student_id', $s->id)->value('status'));
        $this->assertSame(1, $l1->fresh()->leave_count);
        $this->assertSame(0, $l1->fresh()->absent_count);

        // already decided → cannot be decided again
        $this->actingAs($this->teacher)->patch(route('attendance.leaves.reject', $leave))->assertStatus(422);
    }

    public function test_leave_can_be_filed_and_is_validated(): void
    {
        $this->actingAs($this->teacher)
            ->post(route('attendance.leaves.store'), ['student_id' => $this->students[0]->id, 'from_date' => '2026-11-10', 'to_date' => '2026-11-09', 'reason' => ''])
            ->assertSessionHasErrors(['to_date', 'reason']);

        $this->actingAs($this->teacher)
            ->post(route('attendance.leaves.store'), ['student_id' => $this->students[0]->id, 'from_date' => '2026-11-10', 'to_date' => '2026-11-11', 'reason' => 'Sister’s wedding'])
            ->assertRedirect(route('attendance.leaves.index'));
        $this->assertSame('pending', LeaveRequest::sole()->status);
    }

    public function test_defaulters_lists_students_below_threshold_with_streaks(): void
    {
        // Student 1 absent 3 days running, then present: 40% ; others 100%
        foreach (range(5, 1) as $i => $daysAgo) {
            $this->lecture(today()->subDays($daysAgo + 7)->toDateString(), $this->statuses($i < 3 ? 'A' : 'P', 'P', 'P'));
        }

        $res = $this->actingAs($this->principal)->get(route('attendance.defaulters'))->assertOk();
        $res->assertSee('Student 1')->assertDontSee('Student 2')->assertSee('40.0%');
        $res->assertSee('3d');

        $this->actingAs($this->principal)->get(route('attendance.defaulters.export'))
            ->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $this->actingAs($this->principal)
            ->post(route('attendance.defaulters.notify'), ['student_ids' => [$this->students[0]->id]])
            ->assertSessionHas('success');
        $this->assertTrue(GuardianAlert::where('kind', 'short_attendance')->where('student_id', $this->students[0]->id)->exists());
    }

    public function test_dashboards_render_for_principal_and_teacher(): void
    {
        $this->lecture(today()->subDays(1)->toDateString(), $this->statuses('P', 'A', 'L'));
        $this->lecture(today()->subDays(2)->toDateString(), $this->statuses('P', 'P', 'E'), $this->otherCourse);
        TimetableSlot::create(['course_id' => $this->course->id, 'weekday' => 1, 'starts_at' => '08:00', 'ends_at' => '08:45']);

        foreach ([$this->principal, $this->teacher] as $user) {
            $this->actingAs($user)->get(route('attendance.index'))->assertOk();
            $this->actingAs($user)->get(route('attendance.index', ['date' => today()->next('Monday')->toDateString()]))->assertOk()->assertSee('Physics');
            $this->actingAs($user)->get(route('attendance.analytics'))->assertOk()->assertSee('Attendance rate');
            $this->actingAs($user)->get(route('attendance.register', ['month' => today()->format('Y-m')]))->assertOk();
            $this->actingAs($user)->get(route('attendance.student', $this->students[1]))->assertOk()->assertSee('Student 2');
            $this->actingAs($user)->get(route('attendance.leaves.index'))->assertOk();
        }
    }

    public function test_guardian_alert_outbox_is_principal_only_and_sends_via_gateway(): void
    {
        GuardianAlert::create(['student_id' => $this->students[0]->id, 'kind' => 'absent_today', 'to' => '0300-1234567', 'message' => 'Test', 'status' => 'queued']);
        GuardianAlert::create(['student_id' => $this->students[1]->id, 'kind' => 'absent_today', 'to' => '12', 'message' => 'Bad number', 'status' => 'queued']);

        $this->actingAs($this->teacher)->get(route('attendance.alerts'))->assertForbidden();
        $this->actingAs($this->principal)->get(route('attendance.alerts'))->assertOk()->assertSee('Send 2 queued');

        $this->actingAs($this->principal)->post(route('attendance.alerts.send'))->assertSessionHas('error');
        $this->assertSame(1, GuardianAlert::where('status', 'sent')->count());
        $this->assertSame(1, GuardianAlert::where('status', 'failed')->count());
    }

    public function test_editor_and_guest_cannot_reach_attendance(): void
    {
        $this->get(route('attendance.index'))->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create(['role' => 'editor', 'is_active' => true]))->get(route('attendance.index'))->assertForbidden();
    }
}
