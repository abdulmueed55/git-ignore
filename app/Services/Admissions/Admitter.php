<?php

namespace App\Services\Admissions;

use App\Models\ActivityLog;
use App\Models\AdmissionApplication;
use App\Models\Section;
use App\Models\Student;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/** Turns a selected applicant into an enrolled student. */
class Admitter
{
    public function admit(AdmissionApplication $app): Student
    {
        return DB::transaction(function () use ($app) {
            // Lock the application so a double click or two officers cannot admit it twice.
            $app = AdmissionApplication::with(['student', 'program', 'cycle.academicSession'])->lockForUpdate()->findOrFail($app->id);
            if ($app->student) {
                return $app->student;
            }
            if (! in_array($app->status, ['selected', 'verified', 'waitlisted'], true)) {
                throw new RuntimeException('Only selected or verified applicants can be admitted.');
            }
            $seats = (int) ($app->program->seats ?? 0);
            if ($seats > 0) {
                $taken = AdmissionApplication::where('admission_cycle_id', $app->admission_cycle_id)->where('program_id', $app->program_id)->where('status', 'admitted')->count();
                if ($taken >= $seats) {
                    throw new RuntimeException("All {$seats} seats for {$app->program->code} are filled.");
                }
            }

            $sessionId = $app->cycle->academic_session_id;

            $section = Section::where('program_id', $app->program_id)
                ->withCount('students')
                ->orderByRaw('CASE WHEN academic_session_id = ? THEN 0 ELSE 1 END', [$sessionId])
                ->orderBy('year')
                ->orderBy('students_count')
                ->first();

            $session = $section?->academicSession ?? $app->cycle->academicSession;
            $yy = substr($session->name, 2, 2);
            $prefix = sprintf('%s-%s-', $app->program->code, $yy);
            $last = Student::where('roll_no', 'like', $prefix.'%')->lockForUpdate()->pluck('roll_no')
                ->map(fn ($r) => (int) substr($r, strlen($prefix)))->max() ?? 0;

            $student = Student::create([
                'program_id' => $app->program_id,
                'section_id' => $section?->id,
                'academic_session_id' => $session->id,
                'admission_application_id' => $app->id,
                'roll_no' => $prefix.str_pad((string) ($last + 1), 3, '0', STR_PAD_LEFT),
                'name' => $app->name,
                'father_name' => $app->father_name,
                'cnic' => $app->cnic,
                'father_cnic' => $app->father_cnic,
                'date_of_birth' => $app->date_of_birth,
                'gender' => $app->gender ?? 'male',
                'phone' => $app->phone,
                'guardian_phone' => $app->guardian_phone,
                'email' => $app->email,
                'address' => $app->address,
                'city' => $app->city,
                'domicile' => $app->domicile,
                'status' => 'active',
            ]);

            // Student portal account, ready at once: roll number + the PIN the applicant already has.
            // The PIN hash is copied as-is (never seen in plain text) and must be changed on first sign-in.
            $pinHash = $app->getRawOriginal('access_pin');
            if ($pinHash) {
                $student->forceFill(['password' => $pinHash, 'must_change_password' => true])->save();
            }

            $app->update(['status' => 'admitted', 'reviewed_by' => auth()->id(), 'reviewed_at' => now()]);
            ActivityLog::record('admissions.admitted', "Admitted {$app->name} as {$student->roll_no}", $app);

            return $student;
        });
    }
}
