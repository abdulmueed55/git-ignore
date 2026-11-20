<?php

namespace App\Http\Controllers\Admissions;

use App\Http\Controllers\Controller;
use App\Models\ApplicationDocument;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Applicant documents live on the private disk; only admissions staff can open them. */
class DocumentController extends Controller
{
    public function show(Request $request, ApplicationDocument $document): StreamedResponse
    {
        abort_unless(Storage::disk('local')->exists($document->path), 404, 'File not found.');
        $name = $document->application->tracking_no.'-'.$document->type.'.'.pathinfo($document->path, PATHINFO_EXTENSION);

        return Storage::disk('local')->response($document->path, $name, [
            'Content-Type' => $document->mime,
            'Cache-Control' => 'private, max-age=600',
            'X-Content-Type-Options' => 'nosniff',
        ], $request->boolean('download') ? 'attachment' : 'inline');
    }
}
