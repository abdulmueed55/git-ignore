<?php

namespace Tests\Feature\Attendance;

use App\Models\AcademicSession;
use App\Models\Course;
use App\Models\Lecture;
use App\Models\Program;
use App\Models\Section;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use App\Services\Attendance\VoiceAttendance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class VoiceAttendanceTest extends TestCase
{
    use RefreshDatabase;

    private User $teacher;

    private User $otherTeacher;

    private Course $course;

    /** @var Collection<string, Student> keyed by name */
    private Collection $s;

    protected function setUp(): void
    {
        parent::setUp();
        config(['ai.openai.key' => null, 'ai.gemini.key' => null, 'ai.anthropic.key' => null, 'ai.provider' => 'auto']);

        $this->teacher = User::factory()->create(['role' => 'teacher', 'is_active' => true]);
        $this->otherTeacher = User::factory()->create(['role' => 'teacher', 'is_active' => true]);

        $session = AcademicSession::create(['name' => '2026–2028', 'starts_on' => now()->subYear()->toDateString(), 'ends_on' => now()->addYear()->toDateString(), 'is_current' => true]);
        $program = Program::create(['name' => 'F.Sc. Pre-Medical', 'code' => 'FSC-PM', 'level' => 'intermediate']);
        $section = Section::create(['program_id' => $program->id, 'academic_session_id' => $session->id, 'name' => 'Part-I · A', 'room' => 'B-2']);
        $subject = Subject::create(['name' => 'Biology', 'code' => 'BIO-I']);
        $this->course = Course::create(['section_id' => $section->id, 'subject_id' => $subject->id, 'teacher_id' => $this->teacher->id]);

        $names = ['Ahmed Raza', 'Bilal Khan', 'Hamza Ali', 'Muhammad Usman', 'Ayesha Noor', 'Fatima Zahra', 'Zainab Bibi', 'Usman Tariq'];
        $this->s = collect($names)->mapWithKeys(fn ($n, $i) => [$n => Student::create([
            'program_id' => $program->id, 'section_id' => $section->id, 'academic_session_id' => $session->id,
            'roll_no' => sprintf('FSC-PM-26-%03d', $i + 1), 'name' => $n, 'father_name' => 'Father '.$i,
            'guardian_phone' => '0300-1234567', 'status' => 'active',
        ])]);
    }

    private function parse(string $said): array
    {
        $r = (new VoiceAttendance(null))->parse($said, $this->s->values());
        $r['by_name'] = collect($r['marks'])->mapWithKeys(fn ($m) => [$this->s->firstWhere('id', $m['id'])->name => $m['status']])->all();

        return $r;
    }

    public function test_roman_urdu_sentence_marks_absent_leave_and_rest_present(): void
    {
        $r = $this->parse('Ahmed Raza aur Bilal absent hain, Hamza ki chutti hai, baqi sab present');

        $this->assertSame(['Ahmed Raza' => 'A', 'Bilal Khan' => 'A', 'Hamza Ali' => 'E'], $r['by_name']);
        $this->assertSame('P', $r['everyone_else']);
        $this->assertSame([], $r['unclear']);
        $this->assertSame('Built-in matcher', $r['engine']);
    }

    public function test_status_first_with_roll_numbers_and_no_punctuation(): void
    {
        $r = $this->parse('absent roll 2 3 leave Ayesha late Zainab');

        $this->assertSame(['Bilal Khan' => 'A', 'Hamza Ali' => 'A', 'Ayesha Noor' => 'E', 'Zainab Bibi' => 'L'], $r['by_name']);
        $this->assertSame('P', $r['everyone_else']);
    }

    public function test_rest_absent_and_ghair_hazir(): void
    {
        $r = $this->parse('baqi sab ghair hazir, Fatima Zahra aur Usman Tariq present');

        $this->assertSame('A', $r['everyone_else']);
        $this->assertSame(['Fatima Zahra' => 'P', 'Usman Tariq' => 'P'], $r['by_name']);
    }

    public function test_ambiguous_first_name_is_not_guessed(): void
    {
        $r = $this->parse('Usman absent hai');

        $this->assertSame([], $r['marks']);
        $this->assertCount(1, $r['unclear']);
        $this->assertStringContainsString('2 students', $r['unclear'][0]);

        $this->assertSame(['Muhammad Usman' => 'A'], $this->parse('Muhammad Usman absent')['by_name']);
    }

    public function test_endpoint_returns_marks_and_saves_nothing(): void
    {
        $this->actingAs($this->teacher)
            ->postJson(route('attendance.voice', $this->course), ['transcript' => 'Zainab nahi aayi, Hamza late'])
            ->assertOk()
            ->assertJsonPath('everyone_else', 'P')
            ->assertJsonFragment(['id' => $this->s['Hamza Ali']->id, 'status' => 'L'])
            ->assertJsonFragment(['id' => $this->s['Zainab Bibi']->id, 'status' => 'A']);

        $this->assertSame(0, Lecture::count());
    }

    public function test_endpoint_is_limited_to_the_courses_teacher_and_needs_words(): void
    {
        $this->actingAs($this->otherTeacher)
            ->postJson(route('attendance.voice', $this->course), ['transcript' => 'Ahmed absent'])
            ->assertForbidden();

        $this->actingAs($this->teacher)
            ->postJson(route('attendance.voice', $this->course), ['transcript' => ''])
            ->assertUnprocessable()->assertJsonValidationErrors('transcript');
    }

    public function test_openai_answer_is_used_and_unknown_ids_are_dropped(): void
    {
        config(['ai.openai.key' => 'sk-test']);
        Http::fake(['api.openai.com/*' => Http::response(['choices' => [['message' => ['content' => json_encode([
            'marks' => [
                ['student_id' => $this->s['Ayesha Noor']->id, 'status' => 'A', 'heard' => 'عائشہ'],
                ['student_id' => 999999, 'status' => 'A', 'heard' => 'ghost'],
                ['student_id' => $this->s['Bilal Khan']->id, 'status' => 'X', 'heard' => 'bad status'],
            ],
            'everyone_else' => 'P',
            'unclear' => [],
        ])]]]])]);

        $this->actingAs($this->teacher)
            ->postJson(route('attendance.voice', $this->course), ['transcript' => 'عائشہ غیر حاضر ہے، باقی سب حاضر'])
            ->assertOk()
            ->assertJsonPath('engine', 'OpenAI '.config('ai.openai.model'))
            ->assertJsonCount(1, 'marks')
            ->assertJsonPath('marks.0.id', $this->s['Ayesha Noor']->id);

        Http::assertSent(fn ($req) => str_contains($req->url(), '/chat/completions')
            && $req['response_format']['type'] === 'json_schema'
            && str_contains($req['messages'][1]['content'], 'FSC-PM-26-005'));
    }

    public function test_falls_back_to_built_in_matcher_when_the_ai_key_is_rejected(): void
    {
        config(['ai.gemini.key' => 'bad-key']);
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response(['error' => 'denied'], 403)]);

        $this->actingAs($this->teacher)
            ->postJson(route('attendance.voice', $this->course), ['transcript' => 'Ahmed Raza absent'])
            ->assertOk()
            ->assertJsonPath('engine', 'Built-in matcher')
            ->assertJsonPath('marks.0.id', $this->s['Ahmed Raza']->id)
            ->assertJsonPath('warning', fn ($w) => str_contains($w, 'GEMINI_API_KEY'));
    }

    public function test_register_page_shows_speak_attendance_button(): void
    {
        $this->actingAs($this->teacher)
            ->get(route('attendance.mark', ['course' => $this->course, 'time' => '08:00']))
            ->assertOk()->assertSee('Speak attendance')->assertSee('Built-in matcher');
    }
}
