<?php

namespace App\Http\Controllers\Academics;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class ActivityController extends Controller
{
    public const MODULES = [
        'auth' => 'Sign-ins', 'admissions' => 'Admissions', 'attendance' => 'Attendance', 'students' => 'Students',
        'academics' => 'Academics', 'staff' => 'Staff', 'studio' => 'Website studio',
    ];

    public function __invoke(Request $request): View
    {
        $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'user' => ['nullable', 'integer'],
            'module' => ['nullable', 'string', 'max:32'],
        ]);

        $logs = ActivityLog::with(['user', 'subject'])
            ->when($request->filled('user'), fn ($q) => $q->where('user_id', $request->integer('user')))
            ->when($request->filled('module'), fn ($q) => $q->where('action', 'like', $request->input('module').'.%'))
            ->when($request->filled('from'), fn ($q) => $q->where('created_at', '>=', Carbon::parse($request->input('from'))->startOfDay()))
            ->when($request->filled('to'), fn ($q) => $q->where('created_at', '<=', Carbon::parse($request->input('to'))->endOfDay()))
            ->latest()->latest('id')
            ->paginate(50)->withQueryString();

        $since = now()->subDays(13)->startOfDay();
        $daily = ActivityLog::where('created_at', '>=', $since)->get(['created_at'])
            ->groupBy(fn ($l) => $l->created_at->format('Y-m-d'))->map->count();
        $spark = collect(range(13, 0))->map(fn ($i) => $daily[now()->subDays($i)->format('Y-m-d')] ?? 0)->all();

        return view('system.activity', [
            'logs' => $logs,
            'days' => $logs->getCollection()->groupBy(fn ($l) => $l->created_at->format('Y-m-d')),
            'users' => User::whereIn('id', ActivityLog::select('user_id')->distinct())->orderBy('name')->pluck('name', 'id'),
            'modules' => self::MODULES,
            'spark' => $spark,
            'todayCount' => $daily[now()->format('Y-m-d')] ?? 0,
            'weekCount' => array_sum(array_slice($spark, -7)),
        ]);
    }

    /** Link to whatever the log entry is about, if that screen exists. */
    public static function subjectUrl(ActivityLog $log): ?string
    {
        $s = $log->subject;
        if (! $s) {
            return null;
        }
        $map = [
            \App\Models\Student::class => 'students.show',
            \App\Models\Program::class => 'academics.programs.show',
            \App\Models\Section::class => 'academics.sections.show',
            \App\Models\AdmissionApplication::class => 'admissions.show',
            \App\Models\User::class => 'staff.edit',
            \App\Models\Subject::class => 'academics.subjects.edit',
        ];
        $route = $map[$s::class] ?? null;

        return $route && \Route::has($route) ? route($route, $s) : null;
    }
}
