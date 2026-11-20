<?php

namespace App\Http\Controllers\Attendance;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Student;
use App\Services\Attendance\Scope;
use App\Services\Attendance\VoiceAttendance;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * "Speak attendance": the teacher says who is absent / late / on leave and gets
 * back the marks for the register. Nothing is saved here — the teacher checks
 * the register and presses Save as usual.
 */
class VoiceController extends Controller
{
    public function __invoke(Request $request, Course $course): JsonResponse
    {
        abort_unless(Scope::canMark($request->user(), $course), 403, 'This course is not assigned to you.');

        $data = $request->validate([
            'transcript' => ['required', 'string', 'min:2', 'max:2000'],
        ], [
            'transcript.required' => 'Say or type who is absent, late or on leave.',
        ]);

        $roster = Student::where('section_id', $course->section_id)->where('status', 'active')->orderBy('roll_no')->get();

        return response()->json(VoiceAttendance::make()->parse($data['transcript'], $roster));
    }
}
