<?php

namespace App\Http\Controllers\Studio;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\HeroSlide;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class SlideController extends Controller
{
    public function index(): View
    {
        return view('studio.slides.index', ['slides' => HeroSlide::orderBy('sort_order')->get()]);
    }

    public function create(): View
    {
        return view('studio.slides.form', ['slide' => new HeroSlide(['is_active' => true, 'sort_order' => (HeroSlide::max('sort_order') ?? 0) + 1])]);
    }

    public function store(Request $request): RedirectResponse
    {
        $slide = new HeroSlide;
        $this->fill($slide, $request, true);
        ActivityLog::record('studio.slide.created', "Added hero slide “{$slide->heading}”", $slide);

        return redirect()->route('studio.slides.index')->with('success', 'Slide added to the home page.');
    }

    public function edit(HeroSlide $slide): View
    {
        return view('studio.slides.form', compact('slide'));
    }

    public function update(Request $request, HeroSlide $slide): RedirectResponse
    {
        $this->fill($slide, $request, false);

        return redirect()->route('studio.slides.index')->with('success', 'Slide saved.');
    }

    public function destroy(HeroSlide $slide): RedirectResponse
    {
        Storage::disk('public')->delete($slide->image_path);
        $slide->delete();

        return redirect()->route('studio.slides.index')->with('success', 'Slide removed.');
    }

    public function reorder(Request $request): RedirectResponse
    {
        $data = $request->validate(['order' => ['required', 'array'], 'order.*' => ['integer', 'exists:hero_slides,id']]);
        foreach (array_values($data['order']) as $i => $id) {
            HeroSlide::whereKey($id)->update(['sort_order' => $i + 1]);
        }

        return back()->with('success', 'Slide order saved.');
    }

    private function fill(HeroSlide $slide, Request $request, bool $creating): void
    {
        $data = $request->validate([
            'heading' => ['required', 'string', 'min:6', 'max:120'],
            'subheading' => ['required', 'string', 'min:10', 'max:220'],
            'image' => [$creating ? 'required' : 'nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120', 'dimensions:min_width=1000,min_height=400'],
            'cta_label' => ['nullable', 'required_with:cta_url', 'string', 'max:48'],
            'cta_url' => ['nullable', 'required_with:cta_label', 'string', 'max:255', 'regex:#^(https?://|/)#'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:999'],
            'is_active' => ['nullable', 'boolean'],
        ], [
            'image.dimensions' => 'Use a wide image at least 1000 × 400 pixels so it stays sharp on large screens.',
            'cta_url.regex' => 'Links must start with https:// or / (a page on this site).',
        ]);
        if ($request->hasFile('image')) {
            if ($slide->image_path) {
                Storage::disk('public')->delete($slide->image_path);
            }
            $slide->image_path = $request->file('image')->store('slides', 'public');
        }
        $slide->fill([
            'heading' => $data['heading'], 'subheading' => $data['subheading'], 'cta_label' => $data['cta_label'] ?? null,
            'cta_url' => $data['cta_url'] ?? null, 'sort_order' => $data['sort_order'], 'is_active' => $request->boolean('is_active'),
        ])->save();
    }
}
