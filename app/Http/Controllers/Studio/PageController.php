<?php

namespace App\Http\Controllers\Studio;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Studio\Concerns\HtmlSanitizer;
use App\Models\ActivityLog;
use App\Models\Page;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PageController extends Controller
{
    public function index(Request $request): View
    {
        $q = trim((string) $request->query('q'));
        $pages = Page::with('editor')
            ->when($q, fn ($w) => $w->where('title', 'like', "%{$q}%"))
            ->orderByDesc('in_menu')->orderBy('menu_order')->orderBy('title')->get();

        return view('studio.pages.index', compact('pages', 'q'));
    }

    public function create(): View
    {
        return view('studio.pages.form', ['page' => new Page(['in_menu' => true, 'is_published' => true, 'menu_order' => (Page::max('menu_order') ?? 0) + 1])]);
    }

    public function store(Request $request): RedirectResponse
    {
        $page = new Page;
        $this->fill($page, $request);
        ActivityLog::record('studio.page.created', "Created page “{$page->title}”", $page);

        return redirect()->route('studio.pages.edit', $page)->with('success', 'Page created.');
    }

    public function edit(Page $page): View
    {
        return view('studio.pages.form', compact('page'));
    }

    public function update(Request $request, Page $page): RedirectResponse
    {
        $this->fill($page, $request);
        ActivityLog::record('studio.page.updated', "Updated page “{$page->title}”", $page);

        return redirect()->route('studio.pages.edit', $page)->with('success', 'Page saved.');
    }

    public function destroy(Page $page): RedirectResponse
    {
        ActivityLog::record('studio.page.deleted', "Deleted page “{$page->title}”");
        $page->delete();

        return redirect()->route('studio.pages.index')->with('success', 'Page deleted.');
    }

    private function fill(Page $page, Request $request): void
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'min:3', 'max:120'],
            'slug' => ['nullable', 'alpha_dash', 'max:120', Rule::unique('pages', 'slug')->ignore($page->id)],
            'summary' => ['required', 'string', 'min:20', 'max:400'],
            'body' => ['required', 'string', 'min:30'],
            'meta_description' => ['required', 'string', 'min:30', 'max:300'],
            'menu_order' => ['required', 'integer', 'min:0', 'max:999'],
            'in_menu' => ['nullable', 'boolean'],
            'is_published' => ['nullable', 'boolean'],
        ]);
        $body = HtmlSanitizer::clean($data['body']);
        if (mb_strlen(trim(strip_tags($body))) < 20) {
            throw ValidationException::withMessages(['body' => 'The page body is empty after removing unsafe content.']);
        }
        $slug = Str::slug(($data['slug'] ?? null) ?: $data['title']);
        if (Page::where('slug', $slug)->where('id', '!=', $page->id)->exists()) {
            throw ValidationException::withMessages(['slug' => 'Another page already uses this address.']);
        }
        $page->fill([
            'title' => $data['title'], 'slug' => $slug, 'summary' => $data['summary'], 'body' => $body,
            'meta_description' => $data['meta_description'], 'menu_order' => $data['menu_order'],
            'in_menu' => $request->boolean('in_menu'), 'is_published' => $request->boolean('is_published'),
            'updated_by' => $request->user()->id,
        ])->save();
    }
}
