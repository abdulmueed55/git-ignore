<?php

namespace Tests\Feature\Admissions;

use App\Models\AcademicSession;
use App\Models\AdmissionApplication;
use App\Models\AdmissionCycle;
use App\Models\Program;
use App\Models\Section;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

trait AdmissionTestHelpers
{
    protected AcademicSession $session;

    protected Program $bs;

    protected Program $fsc;

    protected AdmissionCycle $cycle;

    protected function setUpAdmissions(): void
    {
        $this->session = AcademicSession::create(['name' => '2026–2030', 'starts_on' => '2026-10-01', 'ends_on' => '2030-07-31', 'is_current' => true]);
        $this->bs = Program::create(['code' => 'BSCS', 'name' => 'BS Computer Science', 'level' => 'bs', 'duration_years' => 4, 'seats' => 3, 'min_percentage' => 50, 'merit_weights' => ['academic' => 0.7, 'matric' => 0.3, 'hafiz_bonus' => 20]]);
        $this->fsc = Program::create(['code' => 'FSC-PE', 'name' => 'F.Sc. Pre-Engineering', 'level' => 'intermediate', 'seats' => 80, 'min_percentage' => 60, 'merit_weights' => ['academic' => 1.0, 'hafiz_bonus' => 20]]);
        Section::create(['program_id' => $this->bs->id, 'academic_session_id' => $this->session->id, 'name' => 'Semester 1', 'year' => 1]);
        $this->cycle = AdmissionCycle::create([
            'academic_session_id' => $this->session->id, 'title' => 'Spring 2027 Intake', 'opens_at' => now()->subDays(5), 'closes_at' => now()->addDays(10),
            'application_fee' => 2000, 'status' => 'open', 'program_ids' => [$this->bs->id, $this->fsc->id], 'instructions' => 'Apply online with clear document photos.',
        ]);
    }

    protected function staff(string $role): User
    {
        return User::create(['name' => ucfirst($role).' User', 'email' => $role.'@test.pk', 'password' => Hash::make('password'), 'role' => $role, 'is_active' => true]);
    }

    /** A submitted application with document data already extracted. */
    protected function application(array $form = [], array $docOverrides = [], ?Program $program = null): AdmissionApplication
    {
        $program ??= $this->bs;
        $truth = [
            'name' => 'Usman Tariq', 'father_name' => 'Tariq Mehmood', 'cnic' => '37405-1234567-1', 'father_cnic' => '37405-7654321-3',
            'date_of_birth' => '2006-05-14', 'gender' => 'male', 'matric_board' => 'BISE Rawalpindi', 'matric_roll_no' => '412233',
            'matric_year' => 2022, 'matric_obtained' => 935, 'matric_total' => 1100, 'inter_board' => 'BISE Rawalpindi', 'inter_roll_no' => '512233',
            'inter_year' => 2024, 'inter_obtained' => 880, 'inter_total' => 1100, 'domicile' => 'Rawalpindi',
        ];
        $app = AdmissionApplication::create(array_merge($truth, [
            'admission_cycle_id' => $this->cycle->id, 'program_id' => $program->id, 'tracking_no' => AdmissionApplication::newTrackingNo(),
            'access_pin' => '123456', 'phone' => '0300-1234567', 'email' => 'u@test.pk', 'quota' => 'open', 'status' => 'submitted', 'submitted_at' => now(),
        ], $form));

        $docs = [
            'cnic' => ['name' => $truth['name'], 'father_name' => $truth['father_name'], 'cnic_number' => $truth['cnic'], 'date_of_birth' => $truth['date_of_birth'], 'gender' => 'male'],
            'father_cnic' => ['name' => $truth['father_name'], 'father_name' => 'Ghulam Rasool', 'cnic_number' => $truth['father_cnic'], 'date_of_birth' => '1975-01-01', 'gender' => 'male'],
            'matric_dmc' => ['name' => $truth['name'], 'father_name' => $truth['father_name'], 'board' => 'BISE Rawalpindi', 'roll_no' => '412233', 'year' => 2022, 'group' => 'Science', 'obtained' => 935, 'total' => 1100, 'date_of_birth' => '14-05-2006'],
            'inter_dmc' => ['name' => $truth['name'], 'father_name' => $truth['father_name'], 'board' => 'Board of Intermediate & Secondary Education, Rawalpindi', 'roll_no' => '512233', 'year' => 2024, 'group' => 'ICS', 'obtained' => 880, 'total' => 1100, 'date_of_birth' => null],
            'domicile' => ['name' => $truth['name'], 'father_name' => $truth['father_name'], 'district' => 'Rawalpindi'],
        ];
        foreach ($docs as $type => $data) {
            $data = array_merge($data, $docOverrides[$type] ?? []);
            $app->documents()->create([
                'type' => $type, 'path' => "admissions/{$app->id}/{$type}.jpg", 'mime' => 'image/jpeg', 'size' => 1000,
                'extraction_status' => 'done', 'extracted' => $data + ['document_type_matches' => true, 'confidence' => 95, 'issues' => []], 'confidence' => 95, 'extracted_by' => 'test',
            ]);
        }

        return $app->fresh(['documents', 'program']);
    }
}
