<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Download;
use App\Models\GalleryAlbum;
use App\Models\HeroSlide;
use App\Models\Page;
use App\Models\Post;
use App\Models\Setting;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PortalController extends Controller
{
    public function home(): View
    {
        $upcoming = Post::live()->where('type', 'event')
            ->where(fn ($q) => $q->where('event_ends_at', '>=', now())->orWhere(fn ($w) => $w->whereNull('event_ends_at')->where('event_starts_at', '>=', now()->startOfDay())))
            ->orderBy('event_starts_at')->take(4)->get();

        return view('portal.home', [
            'slide' => HeroSlide::where('is_active', true)->orderBy('sort_order')->first(),
            'slides' => HeroSlide::where('is_active', true)->orderBy('sort_order')->get(),
            'ticker' => Setting::get('ticker_enabled', '1') ? Post::live()->where('show_in_ticker', true)->latest('published_at')->take(8)->get() : collect(),
            'announcements' => Post::live()->whereIn('type', ['announcement', 'news'])->orderByDesc('is_pinned')->latest('published_at')->take(5)->get(),
            'notices' => Post::live()->where('type', 'notice')->orderByDesc('is_pinned')->latest('published_at')->take(6)->get(),
            'events' => $upcoming,
            'downloads' => Download::where('is_published', true)->latest()->take(6)->get(),
            'albums' => GalleryAlbum::where('is_published', true)->with('images')->latest('taken_on')->take(3)->get(),
            'menu' => $this->menu(),
            's' => Setting::publicValues(),
        ]);
    }

    public function post(string $slug): View
    {
        $post = Post::live()->where('slug', $slug)->firstOrFail();
        $post->increment('views');

        return view('portal.post', [
            'post' => $post,
            'related' => Post::live()->where('type', $post->type)->where('id', '!=', $post->id)->latest('published_at')->take(4)->get(),
            'menu' => $this->menu(),
            's' => Setting::publicValues(),
        ]);
    }

    public function page(string $slug): View
    {
        return view('portal.page', [
            'page' => Page::where('is_published', true)->where('slug', $slug)->firstOrFail(),
            'menu' => $this->menu(),
            's' => Setting::publicValues(),
        ]);
    }

    public function download(Download $download): StreamedResponse
    {
        abort_unless($download->is_published && Storage::disk('public')->exists($download->file_path), 404);
        $download->increment('download_count');

        return Storage::disk('public')->download($download->file_path, $download->file_name);
    }

    private function menu()
    {
        return Page::where('is_published', true)->where('in_menu', true)->orderBy('menu_order')->get(['title', 'slug']);
    }
}
