<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\AlbumResource;
use App\Http\Resources\DownloadResource;
use App\Http\Resources\FacultyResource;
use App\Http\Resources\PageResource;
use App\Http\Resources\PostResource;
use App\Http\Resources\SlideResource;
use App\Models\Download;
use App\Models\FacultyProfile;
use App\Models\GalleryAlbum;
use App\Models\HeroSlide;
use App\Models\Page;
use App\Models\Post;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * Read-only content API for the public website. Responses are cached for
 * 60 seconds; only content that is live right now is ever returned.
 */
class ContentController extends Controller
{
    private const TTL = 60;

    private function cached(Request $request, string $key, \Closure $build): JsonResponse
    {
        // Only known parameters reach the query, the cache key and the pagination links:
        // random ?x=1, ?x=2… cannot fill the cache table or leak into cached links.
        $known = array_filter([
            'category' => is_string($c = $request->query('category')) && mb_strlen($c) <= 40 ? $c : null,
            'department' => is_string($d = $request->query('department')) && preg_match('/^[A-Za-z0-9-]{1,16}$/', $d) ? $d : null,
            'when' => $request->query('when') === 'past' ? 'past' : null,
            'page' => ($p = (int) $request->query('page', 1)) > 1 ? min($p, 500) : null,
            'per_page' => $request->has('per_page') ? min(max((int) $request->query('per_page'), 1), 50) : null,
        ], fn ($v) => $v !== null);
        $request->query->replace($known);
        ksort($known);

        $payload = Cache::remember('api:'.$key.':'.md5(http_build_query($known)), self::TTL, fn () => $build()->response($request)->getData(true));

        return response()->json($payload)->header('Cache-Control', 'public, max-age='.self::TTL);
    }

    public function posts(Request $request, string $type): JsonResponse
    {
        $perPage = min(max((int) $request->query('per_page', 10), 1), 50);

        return $this->cached($request, "posts:{$type}", function () use ($type, $perPage, $request) {
            $q = Post::live()->where('type', $type)
                ->when($request->query('category'), fn ($w, $c) => $w->where('category', $c));
            $q = $type === 'event'
                ? $q->when($request->query('when') !== 'past', fn ($w) => $w->where(fn ($x) => $x->where('event_ends_at', '>=', now())->orWhere(fn ($y) => $y->whereNull('event_ends_at')->where('event_starts_at', '>=', now()->startOfDay()))))
                    ->when($request->query('when') === 'past', fn ($w) => $w->where('event_starts_at', '<', now()))
                    ->orderBy('event_starts_at', $request->query('when') === 'past' ? 'desc' : 'asc')
                : $q->orderByDesc('is_pinned')->latest('published_at');

            return PostResource::collection($q->paginate($perPage));
        });
    }

    public function post(string $slug): JsonResponse
    {
        $post = Post::live()->where('slug', $slug)->firstOrFail();
        $post->increment('views');

        return response()->json(['data' => (new PostResource($post))->resolve()]);
    }

    public function page(Request $request, string $slug): JsonResponse
    {
        return $this->cached($request, "page:{$slug}", fn () => new PageResource(Page::where('is_published', true)->where('slug', $slug)->firstOrFail()));
    }

    public function menu(): JsonResponse
    {
        $items = Cache::remember('api:menu', self::TTL, fn () => Page::where('is_published', true)->where('in_menu', true)
            ->orderBy('menu_order')->get(['title', 'slug'])
            ->map(fn ($p) => ['title' => $p->title, 'slug' => $p->slug, 'api' => route('api.pages.show', $p->slug), 'url' => route('portal.page', $p->slug)])->all());

        return response()->json(['data' => $items]);
    }

    public function slides(Request $request): JsonResponse
    {
        return $this->cached($request, 'slides', fn () => SlideResource::collection(HeroSlide::where('is_active', true)->orderBy('sort_order')->get()));
    }

    public function gallery(Request $request): JsonResponse
    {
        return $this->cached($request, 'gallery', fn () => AlbumResource::collection(GalleryAlbum::where('is_published', true)->with('images')->latest('taken_on')->get()));
    }

    public function album(Request $request, string $slug): JsonResponse
    {
        $album = GalleryAlbum::where('is_published', true)->where('slug', $slug)->with('images')->firstOrFail();

        return response()->json(['data' => (new AlbumResource($album))->resolve($request)]);
    }

    public function downloads(Request $request): JsonResponse
    {
        return $this->cached($request, 'downloads', fn () => DownloadResource::collection(Download::where('is_published', true)
            ->when($request->query('category'), fn ($q, $c) => $q->where('category', $c))
            ->orderBy('category')->latest()->get()));
    }

    public function faculty(Request $request): JsonResponse
    {
        return $this->cached($request, 'faculty', fn () => FacultyResource::collection(FacultyProfile::where('is_published', true)->with('department')
            ->when($request->query('department'), fn ($q, $d) => $q->whereHas('department', fn ($w) => $w->where('code', $d)))
            ->orderBy('sort_order')->orderBy('name')->get()));
    }

    public function settings(): JsonResponse
    {
        return response()->json(['data' => Setting::publicValues()])->header('Cache-Control', 'public, max-age='.self::TTL);
    }

    public function ticker(): JsonResponse
    {
        if (! Setting::get('ticker_enabled', '1')) {
            return response()->json(['data' => []]);
        }
        $items = Cache::remember('api:ticker', self::TTL, fn () => Post::live()->where('show_in_ticker', true)->latest('published_at')->take(10)->get()
            ->map(fn ($p) => ['title' => $p->title, 'slug' => $p->slug, 'type' => $p->type, 'url' => route('portal.post', $p->slug)])->all());

        return response()->json(['data' => $items]);
    }
}
