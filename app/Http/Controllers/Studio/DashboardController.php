<?php

namespace App\Http\Controllers\Studio;

use App\Http\Controllers\Controller;
use App\Models\ContactMessage;
use App\Models\Download;
use App\Models\FacultyProfile;
use App\Models\GalleryAlbum;
use App\Models\HeroSlide;
use App\Models\Page;
use App\Models\Post;
use App\Models\Setting;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        $live = Post::live()->orderByDesc('is_pinned')->latest('published_at')->get();

        $scheduled = Post::whereIn('status', ['published', 'scheduled'])->where('published_at', '>', now())
            ->orderBy('published_at')->take(6)->get();
        $expiring = Post::live()->whereNotNull('expires_at')->where('expires_at', '<=', now()->addDays(7))
            ->orderBy('expires_at')->take(6)->get();
        $drafts = Post::where('status', 'draft')->latest('updated_at')->take(5)->get();
        $popular = Post::live()->orderByDesc('views')->take(5)->get();
        $unread = ContactMessage::whereNull('read_at')->latest()->take(4)->get();

        // 5-week publishing strip: two weeks back, three weeks ahead (Mon-start weeks)
        $start = now()->startOfWeek()->subWeeks(2);
        $end = (clone $start)->addWeeks(5)->subDay();
        $byDay = Post::whereIn('status', ['published', 'scheduled'])
            ->whereBetween('published_at', [$start->copy()->startOfDay(), $end->copy()->endOfDay()])
            ->get(['id', 'type', 'title', 'published_at'])
            ->groupBy(fn ($p) => $p->published_at->toDateString());
        $events = Post::where('type', 'event')->whereIn('status', ['published', 'scheduled'])
            ->whereBetween('event_starts_at', [$start->copy()->startOfDay(), $end->copy()->endOfDay()])
            ->get(['id', 'title', 'event_starts_at'])
            ->groupBy(fn ($p) => $p->event_starts_at->toDateString());
        $calendar = [];
        for ($d = $start->copy(); $d->lte($end); $d->addDay()) {
            $key = $d->toDateString();
            $calendar[] = ['date' => $d->copy(), 'posts' => $byDay[$key] ?? collect(), 'events' => $events[$key] ?? collect()];
        }

        return view('studio.dashboard', [
            'live' => $live,
            'ticker' => $live->where('show_in_ticker', true)->values(),
            'pinned' => $live->where('is_pinned', true)->values(),
            'scheduled' => $scheduled,
            'expiring' => $expiring,
            'drafts' => $drafts,
            'popular' => $popular,
            'unread' => $unread,
            'unreadCount' => ContactMessage::whereNull('read_at')->count(),
            'calendar' => $calendar,
            'health' => $this->healthChecks(),
            'totals' => [
                'live' => $live->count(),
                'scheduled' => Post::whereIn('status', ['published', 'scheduled'])->where('published_at', '>', now())->count(),
                'drafts' => Post::where('status', 'draft')->count(),
                'views30' => (int) Post::where('published_at', '>=', now()->subDays(30))->sum('views'),
                'pages' => Page::where('is_published', true)->count(),
                'albums' => GalleryAlbum::where('is_published', true)->count(),
                'downloads' => Download::where('is_published', true)->count(),
                'faculty' => FacultyProfile::where('is_published', true)->count(),
            ],
            'liveByType' => collect(Post::TYPES)->map(fn ($l, $t) => $live->where('type', $t)->count()),
            'tickerEnabled' => (bool) Setting::get('ticker_enabled', true),
            'categories' => PostController::CATEGORIES,
        ]);
    }

    /** Content problems an editor can fix in one click. */
    private function healthChecks(): array
    {
        $checks = [];

        $noCover = Post::live()->where('type', 'event')->whereNull('cover_path')->count();
        if ($noCover) {
            $checks[] = ['warn', "{$noCover} live ".str('event')->plural($noCover).' without a cover image', 'Events with a photo get noticed on the home page.', route('studio.posts.index', ['type' => 'event', 'state' => 'live'])];
        }

        $pastEvents = Post::live()->where('type', 'event')->where(fn ($q) => $q->where('event_ends_at', '<', now())->orWhere(fn ($w) => $w->whereNull('event_ends_at')->where('event_starts_at', '<', now()->subDay())))->whereNull('expires_at')->count();
        if ($pastEvents) {
            $checks[] = ['info', "{$pastEvents} finished ".str('event')->plural($pastEvents).' still listed as upcoming', 'Set an expiry or archive them, or post the results as news.', route('studio.posts.index', ['type' => 'event', 'state' => 'live'])];
        }

        $updated = Setting::get('principal_message_updated_at');
        if (! $updated || Carbon::parse($updated)->lt(now()->subDays(90))) {
            $age = $updated ? Carbon::parse($updated)->diffForHumans() : 'never';
            $checks[] = ['warn', "Principal's message last updated {$age}", 'Visitors read this first — refresh it each term.', route('studio.settings').'#principal'];
        }

        if (! HeroSlide::where('is_active', true)->exists()) {
            $checks[] = ['bad', 'No active hero slide', 'The home page banner will be empty.', route('studio.slides.create')];
        }

        $ticker = Post::live()->where('show_in_ticker', true)->count();
        if ($ticker === 0) {
            $checks[] = ['info', 'News ticker has nothing to show', 'Tick “Show in ticker” on an important announcement.', route('studio.posts.index', ['type' => 'announcement'])];
        }

        $oldNotices = Post::live()->where('type', 'notice')->whereNull('expires_at')->where('published_at', '<', now()->subDays(60))->count();
        if ($oldNotices) {
            $checks[] = ['info', "{$oldNotices} notices older than 60 days have no expiry", 'Old notices crowd the notice board.', route('studio.posts.index', ['type' => 'notice', 'state' => 'live'])];
        }

        $noPdf = Post::live()->where('type', 'notice')->whereNull('attachment_path')->where('title', 'like', '%date sheet%')->count();
        if ($noPdf) {
            $checks[] = ['warn', "{$noPdf} date-sheet notice without a PDF", 'Students look for a printable copy.', route('studio.posts.index', ['type' => 'notice', 'q' => 'date sheet'])];
        }

        if (FacultyProfile::where('is_published', true)->whereNull('qualification')->exists()) {
            $n = FacultyProfile::where('is_published', true)->whereNull('qualification')->count();
            $checks[] = ['info', "{$n} faculty profiles missing qualifications", 'Complete the directory before the new site launches.', route('studio.faculty.index')];
        }

        return $checks;
    }
}
