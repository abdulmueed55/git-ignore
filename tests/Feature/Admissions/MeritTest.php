<?php

namespace Tests\Feature\Admissions;

use App\Models\AdmissionApplication;
use App\Services\Admissions\Admitter;
use App\Services\Admissions\MeritCalculator;
use App\Services\Admissions\MeritListService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MeritTest extends TestCase
{
    use AdmissionTestHelpers, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpAdmissions();
    }

    public function test_intermediate_merit_is_matric_percentage_with_hafiz_bonus(): void
    {
        $calc = new MeritCalculator;
        $app = $this->application(['matric_obtained' => 880, 'matric_total' => 1100], [], $this->fsc);
        $this->assertEqualsWithDelta(80.0, $calc->score($app), 0.001);

        $app->is_hafiz = true;
        $this->assertEqualsWithDelta((880 + 20) / 1100 * 100, $calc->score($app), 0.001);
    }

    public function test_bs_merit_weights_inter_and_matric(): void
    {
        $app = $this->application(['inter_obtained' => 880, 'inter_total' => 1100, 'matric_obtained' => 990, 'matric_total' => 1100]);
        $expected = 0.7 * 80 + 0.3 * 90;
        $this->assertEqualsWithDelta($expected, (new MeritCalculator)->score($app), 0.001);
    }

    public function test_merit_list_selects_top_verified_applicants_within_seats_and_admits(): void
    {
        $calc = new MeritCalculator;
        foreach ([700, 900, 650, 820, 500] as $i => $marks) {
            $a = $this->application(['cnic' => sprintf('37405-00000%02d-1', $i), 'inter_obtained' => $marks, 'status' => 'verified', 'verification_status' => 'clear']);
            $calc->apply($a);
        }

        $service = new MeritListService;
        $list = $service->generate($this->cycle, $this->bs); // 3 seats
        $this->assertSame(3, $list->entries()->count());
        $this->assertSame([900, 820, 700], $list->entries()->with('application')->get()->map(fn ($e) => $e->application->inter_obtained)->all());

        $service->publish($list, now()->addWeek()->toDateString());
        $this->assertSame(3, AdmissionApplication::where('status', 'selected')->count());
        // 650/1100 = 59% ≥ 50% min → waitlisted; 500/1100 = 45% < 50% → untouched
        $this->assertSame(1, AdmissionApplication::where('status', 'waitlisted')->count());

        $top = AdmissionApplication::where('inter_obtained', 900)->first();
        $student = (new Admitter)->admit($top);
        $this->assertSame('BSCS-26-001', $student->roll_no);
        $this->assertNotNull($student->section_id);
        $this->assertSame('admitted', $top->fresh()->status);
    }
}
