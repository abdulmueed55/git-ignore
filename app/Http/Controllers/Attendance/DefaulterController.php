<?php

namespace App\Http\Controllers\Attendance;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Student;
use App\Services\Attendance\AlertService;
use App\Services\Attendance\AttendanceStats;
use App\Services\Attendance\Scope;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DefaulterController extends Controller
{
    use Filters;

    public function index(Request $request, AttendanceStats $stats): View
    {
        $tab = $request->input('tab') === 'risk' ? 'risk' : 'defaulters';
        $list = $this->build($request, $stats);
        $threshold = config('attendance.threshold');

        return view('attendance.defaulters', [
            'tab' => $tab,
            'rows' => $tab === 'risk' ? $list['risk'] : $list['defaulters'],
            'counts' => ['defaulters' => $list['defaulters']->count(), 'risk' => $list['risk']->count(), 'total' => $list['total']],
            'threshold' => $threshold,
            'margin' => config('attendance.warning_margin'),
            'options' => $this->filterOptions($request),
            'q' => $request->input('q'),
        ]);
    }

    public function export(Request $request, AttendanceStats $stats): StreamedResponse
    {
        $rows = $this->build($request, $stats)[$request->input('tab') === 'risk' ? 'risk' : 'defaulters'];

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Roll no', 'Name', 'Father name', 'Class', 'Attendance %', 'Present', 'Late', 'Absent', 'Leave', 'Longest absence streak (days)', 'Last present', 'Guardian phone']);
            foreach ($rows as $r) {
                $s = $r['student'];
                fputcsv($out, [$s->roll_no, $s->name, $s->father_name, $s->section?->label(), $r['rate'], $r['P'], $r['L'], $r['A'], $r['E'], $r['streak']['longest'], $r['streak']['last_present'], $s->guardian_phone]);
            }
            fclose($out);
        }, 'attendance-defaulters-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv']);
    }

    public function notify(Request $request, AttendanceStats $stats, AlertService $alerts): RedirectResponse
    {
        $data = $request->validate(['student_ids' => ['required', 'array', 'min:1'], 'student_ids.*' => ['integer']], [
            'student_ids.required' => 'Select at least one student to notify.',
        ]);
        $sectionIds = Scope::sectionIds($request->user());
        $students = Student::whereIn('id', $data['student_ids'])
            ->when($sectionIds !== null, fn ($q) => $q->whereIn('section_id', $sectionIds ?: [0]))->get();
        $n = $alerts->notifyDefaulters($students, $stats->studentRates($students->pluck('id')->all()));
        ActivityLog::record('attendance.notified', "Queued {$n} short-attendance alerts for guardians");

        return back()->with('success', $n ? "{$n} guardian alert(s) queued in the outbox." : 'Those guardians were already notified today.');
    }

    /** @return array{defaulters: Collection, risk: Collection, total: int} */
    private function build(Request $request, AttendanceStats $stats): array
    {
        $f = $this->filters($request, 365);
        $threshold = config('attendance.threshold');
        $margin = config('attendance.warning_margin');
        $sectionIds = Scope::sectionIds($request->user());

        $students = Student::with('section.program')->where('status', 'active')
            ->when($sectionIds !== null, fn ($q) => $q->whereIn('section_id', $sectionIds ?: [0]))
            ->when($f['section_id'], fn ($q) => $q->where('section_id', $f['section_id']))
            ->when($f['program_id'], fn ($q) => $q->where('program_id', $f['program_id']))
            ->when($request->filled('q'), fn ($q) => $q->where(fn ($w) => $w->where('name', 'like', '%'.$request->input('q').'%')->orWhere('roll_no', 'like', '%'.$request->input('q').'%')))
            ->get()->keyBy('id');

        $rates = $stats->studentRates($students->keys()->all(), ['course_ids' => $f['course_ids']]);
        $flagged = collect($rates)->filter(fn ($r) => $r['rate'] !== null && $r['rate'] < $threshold + $margin);
        $days = $stats->dayStatuses($flagged->keys()->all(), now()->subDays(120)->toDateString());

        $rows = $flagged->map(fn ($r, $id) => $r + [
            'student' => $students[$id],
            'streak' => AttendanceStats::streaks($days[$id] ?? []),
            'weekly' => AttendanceStats::weekly($days[$id] ?? []),
        ])->sortBy('rate')->values();

        return [
            'defaulters' => $rows->filter(fn ($r) => $r['rate'] < $threshold)->values(),
            'risk' => $rows->filter(fn ($r) => $r['rate'] >= $threshold)->values(),
            'total' => count($rates),
        ];
    }
}
