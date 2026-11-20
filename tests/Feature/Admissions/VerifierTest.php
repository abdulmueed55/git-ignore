<?php

namespace Tests\Feature\Admissions;

use App\Services\Admissions\CrossDocumentVerifier;
use App\Services\Admissions\Normalizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VerifierTest extends TestCase
{
    use AdmissionTestHelpers, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpAdmissions();
    }

    public function test_consistent_documents_clear_and_verify_the_application(): void
    {
        $app = (new CrossDocumentVerifier)->verify($this->application());

        $this->assertSame('clear', $app->verification_status);
        $this->assertSame('verified', $app->status);
        $this->assertGreaterThanOrEqual(90, $app->verification_score);
        $this->assertSame(0, $app->checks()->whereIn('result', ['mismatch', 'partial'])->count());
        // Board names written differently still match; DOB in d-m-Y on the DMC matches Y-m-d on the form.
        $this->assertSame('match', $app->checks()->where('field', 'inter_board')->value('result'));
        $this->assertSame('match', $app->checks()->where('field', 'date_of_birth')->value('result'));
    }

    public function test_tampered_marks_are_flagged_as_mismatch(): void
    {
        $app = (new CrossDocumentVerifier)->verify($this->application(['matric_obtained' => 1035]));

        $this->assertSame('mismatch', $app->verification_status);
        $this->assertSame('flagged', $app->status);
        $this->assertSame('mismatch', $app->checks()->where('field', 'matric_obtained')->value('result'));
        $this->assertLessThan(90, $app->verification_score);
    }

    public function test_spelling_variant_is_partial_and_goes_to_review(): void
    {
        $app = (new CrossDocumentVerifier)->verify($this->application([], ['matric_dmc' => ['name' => 'Usman Tarik']]));

        $this->assertSame('partial', $app->checks()->where('field', 'name')->value('result'));
        $this->assertSame('review', $app->verification_status);
        $this->assertSame('under_review', $app->status);
    }

    public function test_different_father_name_is_a_mismatch_not_a_spelling_issue(): void
    {
        $app = (new CrossDocumentVerifier)->verify($this->application(['father_name' => 'Shahid Mehmood']));

        $this->assertSame('mismatch', $app->checks()->where('field', 'father_name')->value('result'));
    }

    public function test_duplicate_cnic_in_same_cycle_is_detected(): void
    {
        $this->application();
        $second = $this->application(['name' => 'Usman Tariq']);
        $second = (new CrossDocumentVerifier)->verify($second);

        $check = $second->checks()->where('field', 'duplicate_cnic')->first();
        $this->assertSame('mismatch', $check->result);
        $this->assertStringContainsString('FGLA-', $check->note);
    }

    public function test_overridden_check_survives_reverification(): void
    {
        $verifier = new CrossDocumentVerifier;
        $app = $verifier->verify($this->application(['matric_obtained' => 1035]));
        $app->checks()->where('field', 'matric_obtained')->update(['overridden' => true, 'note' => 'Re-checked original DMC, typo on card']);

        $app = $verifier->verify($app->fresh(['documents', 'program']));
        $this->assertTrue((bool) $app->checks()->where('field', 'matric_obtained')->value('overridden'));
        $this->assertNotSame('mismatch', $app->verification_status);
    }

    public function test_name_normalisation_handles_common_pakistani_variants(): void
    {
        $this->assertSame(100, Normalizer::nameSimilarity('Muhammad Ahmed', 'M. Ahmad'));
        $this->assertSame(100, Normalizer::nameSimilarity('Zain ul Abideen', 'Zain-ul-Abideen'));
        $this->assertSame(100, Normalizer::nameSimilarity('Ali Raza', 'Raza Ali'));
        $this->assertLessThan(80, Normalizer::nameSimilarity('Shahid Mehmood', 'Sajid Mehmood'));
        $this->assertSame('2006-05-14', Normalizer::date('14-05-2006'));
        $this->assertSame('2006-05-14', Normalizer::date('14/05/2006'));
    }
}
