<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\AdmissionCycle;
use App\Models\ContactMessage;
use App\Models\Download;
use App\Models\FacultyProfile;
use App\Models\GalleryAlbum;
use App\Models\HeroSlide;
use App\Models\Page;
use App\Models\Post;
use App\Models\Program;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The public college website (approved design "FGLA Website Directions").
 * Slides, notices, events, faculty, gallery, downloads and contact details come from the
 * Website Studio; fixed text (history, admission policy, achievers) from config/site.php.
 */
class PortalController extends Controller
{
    public function home(): View
    {
        $faculty = $this->facultyList();

        return view('site.home', [
            'slides' => HeroSlide::where('is_active', true)->orderBy('sort_order')->get(),
            'ticker' => Post::live()->where('show_in_ticker', true)->latest('published_at')->first()
                ?? Post::live()->whereIn('type', ['notice', 'announcement'])->latest('published_at')->first(),
            'notices' => $this->noticeQuery()->take(8)->get(),
            'programmes' => $this->programmeList(),
            'faculty' => $faculty,
            'senior' => $faculty->filter(fn ($f) => $this->rank($f->designation) < 2)->values(),
        ]);
    }

    public function about(): View
    {
        return view('site.about', ['faculty' => FacultyProfile::where('is_published', true)->count()]);
    }

    public function admission(): View
    {
        return view('site.admission', [
            'merit' => Post::live()->where(fn ($q) => $q->where('category', 'Admissions')->orWhere('title', 'like', '%merit%'))->latest('published_at')->take(10)->get(),
            'cycle' => AdmissionCycle::active(),
        ]);
    }

    public function programmes(): View
    {
        return view('site.programmes', ['programmes' => $this->programmeList(), 'faculty' => $this->facultyList()]);
    }

    public function faculty(): View
    {
        $all = $this->facultyList();

        return view('site.faculty', [
            'all' => $all,
            'departments' => $all->groupBy(fn ($f) => $f->department?->name ?? 'Other departments')
                ->map(fn ($g) => $g->sortBy(fn ($f) => $this->rank($f->designation) * 1000 + $f->sort_order)->values()),
        ]);
    }

    public function campus(): View
    {
        return view('site.campus', [
            'albums' => GalleryAlbum::where('is_published', true)->with(['images' => fn ($q) => $q->orderBy('sort_order')])->latest('taken_on')->take(6)->get(),
        ]);
    }

    public function notices(Request $request): View
    {
        $type = in_array($request->query('type'), ['notice', 'announcement', 'news'], true) ? $request->query('type') : null;

        return view('site.notices', [
            'posts' => Post::live()->whereIn('type', $type ? [$type] : ['notice', 'announcement', 'news'])
                ->orderByDesc('is_pinned')->latest('published_at')->paginate(15)->withQueryString(),
            'type' => $type,
            'events' => Post::live()->where('type', 'event')
                ->where(fn ($q) => $q->where('event_ends_at', '>=', now())->orWhere(fn ($w) => $w->whereNull('event_ends_at')->where('event_starts_at', '>=', now()->startOfDay())))
                ->orderBy('event_starts_at')->take(6)->get(),
            'downloads' => Download::where('is_published', true)->orderBy('category')->latest()->get()->groupBy('category'),
        ]);
    }

    public function contact(): View
    {
        return view('site.contact');
    }

    public function sendMessage(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'min:3', 'max:120'],
            'email' => ['nullable', 'required_without:phone', 'email:rfc,strict,filter', 'max:160'],
            'phone' => ['nullable', 'required_without:email', 'string', 'max:32', 'regex:/^[0-9+\-\s()]{7,}$/'],
            'subject' => ['required', 'string', 'min:4', 'max:160'],
            'message' => ['required', 'string', 'min:15', 'max:3000'],
            'website' => ['prohibited'], // honeypot
        ], [
            'email.required_without' => 'Give an email or a phone number so the office can reply.',
            'phone.required_without' => 'Give a phone number or an email so the office can reply.',
            'phone.regex' => 'Enter a phone number, e.g. 0300-1234567.',
        ]);
        unset($data['website']);
        ContactMessage::create($data);

        return redirect()->route('site.contact')->with('sent', 'Thank you — your message has reached the college office. We reply within two working days.');
    }

    public function post(string $slug): View
    {
        $post = Post::live()->where('slug', $slug)->firstOrFail();
        $post->increment('views');

        return view('site.post', [
            'post' => $post,
            'related' => Post::live()->where('type', $post->type)->where('id', '!=', $post->id)->latest('published_at')->take(4)->get(),
        ]);
    }

    public function page(string $slug): View
    {
        return view('site.page', ['page' => Page::where('is_published', true)->where('slug', $slug)->firstOrFail()]);
    }

    public function download(Download $download): StreamedResponse
    {
        abort_unless($download->is_published && Storage::disk('public')->exists($download->file_path), 404);
        $download->increment('download_count');

        return Storage::disk('public')->download($download->file_path, $download->file_name);
    }

    // ------------------------------------------------------------------

    private function noticeQuery()
    {
        return Post::live()->whereIn('type', ['notice', 'announcement'])->orderByDesc('is_pinned')->latest('published_at');
    }

    /** @return array{intermediate: Collection, bs: Collection} */
    private function programmeList(): array
    {
        $all = Program::where('is_active', true)->orderBy('name')->get();

        return ['intermediate' => $all->where('level', 'intermediate')->values(), 'bs' => $all->where('level', 'bs')->values()];
    }

    private function facultyList(): Collection
    {
        return FacultyProfile::with('department')->where('is_published', true)->orderBy('sort_order')->get();
    }

    private function rank(?string $designation): int
    {
        return str_starts_with((string) $designation, 'Associate') ? 0 : (str_starts_with((string) $designation, 'Assistant') ? 1 : 2);
    }
}
