<?php

namespace Tests\Feature\Admissions;

use App\Services\Admissions\CrossDocumentVerifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class StaffAccessTest extends TestCase
{
    use AdmissionTestHelpers, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->setUpAdmissions();
    }

    public function test_teachers_and_guests_cannot_open_admissions(): void
    {
        $app = $this->application();
        $this->get(route('admissions.dashboard'))->assertRedirect(route('login'));
        $this->get(route('admissions.documents.show', $app->documents->first()))->assertRedirect(route('login'));

        $this->actingAs($this->staff('teacher'));
        $this->get(route('admissions.dashboard'))->assertForbidden();
        $this->get(route('admissions.show', $app))->assertForbidden();
    }

    public function test_admission_officer_sees_every_screen(): void
    {
        $app = (new CrossDocumentVerifier)->verify($this->application(['matric_obtained' => 1035]));
        $this->actingAs($this->staff('admission_officer'));

        $this->get(route('admissions.dashboard'))->assertOk()->assertSee('Admission funnel');
        $this->get(route('admissions.index'))->assertOk()->assertSee($app->tracking_no);
        $this->get(route('admissions.index', ['q' => '1234567']))->assertOk()->assertSee($app->tracking_no);
        $this->get(route('admissions.show', $app))->assertOk()->assertSee('Form vs documents')->assertSee('Matric marks obtained');
        $this->get(route('admissions.verification'))->assertOk()->assertSee($app->name);
        $this->get(route('admissions.merit.index'))->assertOk();
        $this->get(route('admissions.cycles.index'))->assertOk()->assertSee('Spring 2027 Intake');
        $this->get(route('admissions.cycles.create'))->assertOk();
        $this->get(route('admissions.export'))->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');
    }

    public function test_officer_can_override_correct_and_admit(): void
    {
        $officer = $this->staff('admission_officer');
        $app = (new CrossDocumentVerifier)->verify($this->application(['matric_obtained' => 1035]));
        $check = $app->checks()->where('field', 'matric_obtained')->first();

        $this->actingAs($officer)
            ->post(route('admissions.checks.override', [$app, $check]), ['note' => ''])->assertSessionHasErrors('note');
        $this->post(route('admissions.checks.override', [$app, $check]), ['note' => 'Original DMC seen at counter'])->assertRedirect();
        $this->assertSame('clear', $app->fresh()->verification_status);

        $this->patch(route('admissions.status', $app), ['status' => 'rejected'])->assertSessionHasErrors('remarks');
        $this->post(route('admissions.admit', $app))->assertRedirect();
        $this->assertSame('admitted', $app->fresh()->status);
        $this->assertDatabaseHas('students', ['admission_application_id' => $app->id, 'roll_no' => 'BSCS-26-001']);
        $this->assertDatabaseHas('activity_logs', ['action' => 'admissions.admitted', 'user_id' => $officer->id]);
    }

    public function test_staff_can_stream_private_documents(): void
    {
        $app = $this->application();
        $doc = $app->documents->first();
        Storage::disk('local')->put($doc->path, UploadedFile::fake()->image('x.jpg')->getContent());

        $this->actingAs($this->staff('principal'))->get(route('admissions.documents.show', $doc))->assertOk();
    }

    public function test_cycle_validation_requires_programmes_and_single_open_cycle(): void
    {
        $this->actingAs($this->staff('admission_officer'));
        $this->post(route('admissions.cycles.store'), ['title' => 'Fall 2027 Intake', 'status' => 'open'])
            ->assertSessionHasErrors(['academic_session_id', 'opens_at', 'closes_at', 'program_ids', 'instructions']);

        $this->post(route('admissions.cycles.store'), [
            'title' => 'Fall 2027 Intake', 'academic_session_id' => $this->session->id, 'opens_at' => now()->toDateTimeString(),
            'closes_at' => now()->addMonth()->toDateTimeString(), 'application_fee' => 1500, 'status' => 'open',
            'program_ids' => [$this->bs->id], 'instructions' => 'Upload your documents and keep your PIN safe.',
        ])->assertSessionHasErrors('status');
    }
}
