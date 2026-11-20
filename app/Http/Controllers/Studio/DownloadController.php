<?php

namespace App\Http\Controllers\Studio;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Download;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class DownloadController extends Controller
{
    public function index(Request $request): View
    {
        $cat = $request->query('category');
        $downloads = Download::when(array_key_exists((string) $cat, Download::CATEGORIES), fn ($q) => $q->where('category', $cat))
            ->orderBy('category')->latest()->get();

        return view('studio.downloads.index', [
            'downloads' => $downloads,
            'category' => $cat,
            'counts' => Download::selectRaw('category, count(*) c')->groupBy('category')->pluck('c', 'category'),
        ]);
    }

    public function create(): View
    {
        return view('studio.downloads.form', ['download' => new Download(['is_published' => true, 'category' => 'forms'])]);
    }

    public function store(Request $request): RedirectResponse
    {
        $d = new Download;
        $this->fill($d, $request, true);
        ActivityLog::record('studio.download.created', "Uploaded “{$d->title}”", $d);

        return redirect()->route('studio.downloads.index')->with('success', 'File published to Downloads.');
    }

    public function edit(Download $download): View
    {
        return view('studio.downloads.form', compact('download'));
    }

    public function update(Request $request, Download $download): RedirectResponse
    {
        $this->fill($download, $request, false);

        return redirect()->route('studio.downloads.index')->with('success', 'Download saved.');
    }

    public function destroy(Download $download): RedirectResponse
    {
        Storage::disk('public')->delete($download->file_path);
        $download->delete();

        return redirect()->route('studio.downloads.index')->with('success', 'File removed.');
    }

    private function fill(Download $d, Request $request, bool $creating): void
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'min:4', 'max:160'],
            'category' => ['required', Rule::in(array_keys(Download::CATEGORIES))],
            'file' => [$creating ? 'required' : 'nullable', 'file', 'mimes:pdf,doc,docx,xls,xlsx,jpg,png', 'max:20480'],
            'is_published' => ['nullable', 'boolean'],
        ]);
        if ($request->hasFile('file')) {
            if ($d->file_path) {
                Storage::disk('public')->delete($d->file_path);
            }
            $f = $request->file('file');
            $d->file_path = $f->store('downloads', 'public');
            $d->file_name = $f->getClientOriginalName();
            $d->size = $f->getSize();
        }
        $d->fill(['title' => $data['title'], 'category' => $data['category'], 'is_published' => $request->boolean('is_published')])->save();
    }
}
