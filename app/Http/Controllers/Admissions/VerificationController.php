<?php

namespace App\Http\Controllers\Admissions;

use App\Http\Controllers\Controller;
use App\Models\AdmissionApplication;
use App\Services\Admissions\Extractors;
use Illuminate\Http\Request;
use Illuminate\View\View;

class VerificationController extends Controller
{
    public function __invoke(Request $request): View
    {
        $tab = in_array($request->query('tab'), ['mismatch', 'review', 'clear', 'pending'], true) ? $request->query('tab') : 'mismatch';

        $base = AdmissionApplication::query()->where('status', '!=', 'draft')->whereNotIn('status', ['admitted', 'rejected']);
        $counts = (clone $base)->selectRaw('verification_status, count(*) c')->groupBy('verification_status')->pluck('c', 'verification_status');

        $apps = (clone $base)->with(['program', 'checks' => fn ($q) => $q->whereIn('result', ['mismatch', 'partial', 'missing'])->where('overridden', false), 'documents:id,admission_application_id,type,extraction_status,confidence'])
            ->where('verification_status', $tab === 'pending' ? 'pending' : $tab)
            ->orderBy('verification_score')->orderBy('submitted_at')
            ->paginate(12)->withQueryString();

        return view('admissions.verification', [
            'apps' => $apps, 'tab' => $tab, 'counts' => $counts, 'aiEnabled' => Extractors::aiEnabled(),
        ]);
    }
}
