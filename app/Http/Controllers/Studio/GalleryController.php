<?php

namespace App\Http\Controllers\Studio;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\GalleryAlbum;
use App\Models\GalleryImage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;

class GalleryController extends Controller
{
    public function index(): View
    {
        return view('studio.gallery.index', ['albums' => GalleryAlbum::with('images')->withCount('images')->latest('taken_on')->get()]);
    }

    public function create(): View
    {
        return view('studio.gallery.form', ['album' => new GalleryAlbum(['is_published' => true, 'taken_on' => now()])]);
    }

    public function store(Request $request): RedirectResponse
    {
        $album = new GalleryAlbum;
        $this->fill($album, $request);
        $this->storeImages($album, $request);
        ActivityLog::record('studio.gallery.created', "Created album “{$album->title}”", $album);

        return redirect()->route('studio.gallery.show', $album)->with('success', 'Album created.');
    }

    public function show(GalleryAlbum $gallery): View
    {
        return view('studio.gallery.show', ['album' => $gallery->load('images')]);
    }

    public function edit(GalleryAlbum $gallery): View
    {
        return view('studio.gallery.form', ['album' => $gallery]);
    }

    public function update(Request $request, GalleryAlbum $gallery): RedirectResponse
    {
        $this->fill($gallery, $request);

        return redirect()->route('studio.gallery.show', $gallery)->with('success', 'Album saved.');
    }

    public function destroy(GalleryAlbum $gallery): RedirectResponse
    {
        Storage::disk('public')->delete($gallery->images->pluck('path')->all());
        ActivityLog::record('studio.gallery.deleted', "Deleted album “{$gallery->title}”");
        $gallery->delete();

        return redirect()->route('studio.gallery.index')->with('success', 'Album and its photos deleted.');
    }

    public function upload(Request $request, GalleryAlbum $gallery): RedirectResponse
    {
        $request->validate([
            'images' => ['required', 'array', 'min:1', 'max:30'],
            'images.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:6144'],
        ], ['images.required' => 'Choose at least one photo to upload.']);
        $n = $this->storeImages($gallery, $request);

        return back()->with('success', "{$n} ".str('photo')->plural($n).' added.');
    }

    public function destroyImage(GalleryImage $image): RedirectResponse
    {
        Storage::disk('public')->delete($image->path);
        $image->delete();

        return back()->with('success', 'Photo removed.');
    }

    private function fill(GalleryAlbum $album, Request $request): void
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'min:4', 'max:120'],
            'description' => ['required', 'string', 'min:15', 'max:400'],
            'taken_on' => ['required', 'date', 'before_or_equal:today'],
            'is_published' => ['nullable', 'boolean'],
            'images' => [$album->exists ? 'nullable' : 'required', 'array', 'max:30'],
            'images.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:6144'],
            'captions' => ['nullable', 'array'],
            'captions.*' => ['nullable', 'string', 'max:190'],
        ], ['images.required' => 'An album needs at least one photo.']);
        $slug = Str::slug($data['title']);
        $base = $slug;
        $i = 2;
        while (GalleryAlbum::where('slug', $slug)->where('id', '!=', $album->id)->exists()) {
            $slug = $base.'-'.$i++;
        }
        $album->fill([
            'title' => $data['title'], 'slug' => $album->exists && $album->title === $data['title'] ? $album->slug : $slug,
            'description' => $data['description'], 'taken_on' => $data['taken_on'], 'is_published' => $request->boolean('is_published'),
        ])->save();
        foreach ($data['captions'] ?? [] as $id => $caption) {
            $album->images()->whereKey($id)->update(['caption' => $caption]);
        }
    }

    private function storeImages(GalleryAlbum $album, Request $request): int
    {
        $order = (int) $album->images()->max('sort_order');
        $n = 0;
        foreach ((array) $request->file('images', []) as $file) {
            $album->images()->create(['path' => $file->store("gallery/{$album->id}", 'public'), 'caption' => null, 'sort_order' => ++$order]);
            $n++;
        }

        return $n;
    }
}
