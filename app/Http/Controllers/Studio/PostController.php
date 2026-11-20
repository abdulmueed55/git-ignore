<?php

namespace App\Http\Controllers\Studio;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Studio\Concerns\HtmlSanitizer;
use App\Models\ActivityLog;
use App\Models\Post;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PostController extends Controller
{
    public const CATEGORIES = ['Academics', 'Admissions', 'Examinations', 'Sports', 'Co-curricular', 'Administration', 'Holidays', 'Fees', 'Library', 'Community'];

    public function index(Request $request): View
    {
        $type = in_array($request->query('type'), array_keys(Post::TYPES), true) ? $request->query('type') : null;
        $state = $request->query('state');
        $q = trim((string) $request->query('q'));

        $posts = Post::query()->with('author')->ofType($type)
            ->when($q, fn ($w) => $w->where(fn ($x) => $x->where('title', 'like', "%{$q}%")->orWhere('excerpt', 'like', "%{$q}%")->orWhere('category', 'like', "%{$q}%")))
            ->when($request->query('category'), fn ($w, $c) => $w->where('category', $c))
            ->when($state === 'live', fn ($w) => $w->live())
            ->when($state === 'scheduled', fn ($w) => $w->whereIn('status', ['published', 'scheduled'])->where('published_at', '>', now()))
            ->when($state === 'expired', fn ($w) => $w->whereIn('status', ['published', 'scheduled'])->where('expires_at', '<=', now()))
            ->when(in_array($state, ['draft', 'archived'], true), fn ($w) => $w->where('status', $state))
            ->orderByDesc('is_pinned')
            ->orderByRaw('COALESCE(published_at, created_at) DESC')
            ->paginate(15)->withQueryString();

        $base = Post::query()->ofType($type);
        $counts = [
            'all' => (clone $base)->count(),
            'live' => (clone $base)->live()->count(),
            'scheduled' => (clone $base)->whereIn('status', ['published', 'scheduled'])->where('published_at', '>', now())->count(),
            'draft' => (clone $base)->where('status', 'draft')->count(),
            'expired' => (clone $base)->whereIn('status', ['published', 'scheduled'])->where('expires_at', '<=', now())->count(),
            'archived' => (clone $base)->where('status', 'archived')->count(),
        ];

        return view('studio.posts.index', [
            'posts' => $posts,
            'type' => $type,
            'state' => $state,
            'q' => $q,
            'counts' => $counts,
            'categories' => self::CATEGORIES,
        ]);
    }

    public function create(Request $request): View
    {
        $type = in_array($request->query('type'), array_keys(Post::TYPES), true) ? $request->query('type') : 'announcement';
        $post = new Post(['type' => $type, 'status' => 'published', 'published_at' => now(), 'category' => $type === 'event' ? 'Co-curricular' : 'Administration']);

        return view('studio.posts.form', ['post' => $post, 'categories' => self::CATEGORIES]);
    }

    public function store(Request $request): RedirectResponse
    {
        $post = new Post(['author_id' => $request->user()->id]);
        $this->fill($post, $request);
        $post->save();
        ActivityLog::record('studio.post.created', "Created {$post->typeLabel()} “{$post->title}”", $post);

        return redirect()->route('studio.posts.edit', [$post, 'type' => $post->type])
            ->with('success', $this->savedMessage($post));
    }

    public function edit(Post $post): View
    {
        return view('studio.posts.form', ['post' => $post, 'categories' => self::CATEGORIES]);
    }

    public function update(Request $request, Post $post): RedirectResponse
    {
        $this->fill($post, $request);
        $post->save();
        ActivityLog::record('studio.post.updated', "Updated {$post->typeLabel()} “{$post->title}”", $post);

        return redirect()->route('studio.posts.edit', [$post, 'type' => $post->type])->with('success', $this->savedMessage($post));
    }

    public function destroy(Post $post): RedirectResponse
    {
        $type = $post->type;
        Storage::disk('public')->delete(array_filter([$post->cover_path, $post->attachment_path]));
        ActivityLog::record('studio.post.deleted', "Deleted {$post->typeLabel()} “{$post->title}”");
        $post->delete();

        return redirect()->route('studio.posts.index', ['type' => $type])->with('success', 'Deleted. It is no longer on the website.');
    }

    public function duplicate(Post $post): RedirectResponse
    {
        $copy = $post->replicate(['slug', 'views']);
        $copy->title = Str::limit($post->title, 240, '').' (copy)';
        $copy->slug = null;
        $copy->status = 'draft';
        $copy->is_pinned = false;
        $copy->show_in_ticker = false;
        $copy->views = 0;
        $copy->author_id = auth()->id();
        $copy->save();

        return redirect()->route('studio.posts.edit', [$copy, 'type' => $copy->type])->with('success', 'Copy created as a draft.');
    }

    public function togglePin(Post $post): RedirectResponse
    {
        $post->update(['is_pinned' => ! $post->is_pinned]);

        return back()->with('success', $post->is_pinned ? 'Pinned to the top of the website.' : 'Unpinned.');
    }

    public function bulk(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'exists:posts,id'],
            'action' => ['required', Rule::in(['publish', 'archive', 'draft', 'delete'])],
        ], ['ids.required' => 'Select at least one item first.']);

        $posts = Post::whereIn('id', $data['ids'])->get();
        foreach ($posts as $post) {
            match ($data['action']) {
                'publish' => $post->update(['status' => 'published', 'published_at' => $post->published_at && $post->published_at->isPast() ? $post->published_at : now()]),
                'archive' => $post->update(['status' => 'archived', 'is_pinned' => false, 'show_in_ticker' => false]),
                'draft' => $post->update(['status' => 'draft']),
                'delete' => $post->delete(),
            };
        }
        ActivityLog::record('studio.post.bulk', ucfirst($data['action'])." applied to {$posts->count()} posts");

        return back()->with('success', $posts->count().' '.str('item')->plural($posts->count()).' updated.');
    }

    private function fill(Post $post, Request $request): void
    {
        $isEvent = $request->input('type') === 'event';
        $data = $request->validate([
            'type' => ['required', Rule::in(array_keys(Post::TYPES))],
            'title' => ['required', 'string', 'min:6', 'max:180'],
            'slug' => ['nullable', 'string', 'max:190', 'alpha_dash', Rule::unique('posts', 'slug')->ignore($post->id)],
            'excerpt' => ['required', 'string', 'min:20', 'max:400'],
            'body' => ['required', 'string', 'min:30'],
            'category' => ['required', 'string', 'max:48'],
            'publish_mode' => ['required', Rule::in(['now', 'schedule', 'draft'])],
            'published_at' => ['nullable', 'required_if:publish_mode,schedule', 'date'],
            'expires_at' => ['nullable', 'date'],
            'event_starts_at' => [Rule::requiredIf($isEvent), 'nullable', 'date'],
            'event_ends_at' => ['nullable', 'date', 'after_or_equal:event_starts_at'],
            'venue' => [Rule::requiredIf($isEvent), 'nullable', 'string', 'max:190'],
            'cover' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'remove_cover' => ['nullable', 'boolean'],
            'attachment' => ['nullable', 'file', 'mimes:pdf', 'max:10240'],
            'attachment_name' => ['nullable', 'string', 'max:190'],
            'remove_attachment' => ['nullable', 'boolean'],
            'is_pinned' => ['nullable', 'boolean'],
            'show_in_ticker' => ['nullable', 'boolean'],
        ], [
            'published_at.required_if' => 'Pick the date and time this should go live.',
            'event_starts_at.required' => 'Events need a start date and time.',
            'venue.required' => 'Events need a venue so visitors know where to go.',
            'excerpt.min' => 'Write at least a full sentence — the excerpt is shown in lists and link previews.',
        ]);

        $body = HtmlSanitizer::clean($data['body']);
        if (mb_strlen(trim(strip_tags($body))) < 20) {
            throw ValidationException::withMessages(['body' => 'The body is empty after removing unsafe content. Write the full text of the notice.']);
        }

        $publishAt = match ($data['publish_mode']) {
            'now' => $post->exists && $post->published_at?->isPast() && $post->status === 'published' ? $post->published_at : now(),
            'schedule' => Carbon::parse($data['published_at']),
            'draft' => isset($data['published_at']) ? Carbon::parse($data['published_at']) : $post->published_at,
        };
        if (! empty($data['expires_at']) && $publishAt && Carbon::parse($data['expires_at'])->lte($publishAt)) {
            throw ValidationException::withMessages(['expires_at' => 'Expiry must be after the publish time.']);
        }

        $post->fill([
            'type' => $data['type'],
            'title' => $data['title'],
            'slug' => ! empty($data['slug']) ? Str::slug($data['slug']) : ($post->exists ? $post->slug : null),
            'excerpt' => $data['excerpt'],
            'body' => $body,
            'category' => $data['category'],
            'status' => match ($data['publish_mode']) {
                'now' => 'published',
                'schedule' => $publishAt->isFuture() ? 'scheduled' : 'published',
                'draft' => 'draft',
            },
            'published_at' => $publishAt,
            'expires_at' => $data['expires_at'] ?? null,
            'event_starts_at' => $isEvent ? $data['event_starts_at'] : null,
            'event_ends_at' => $isEvent ? ($data['event_ends_at'] ?? null) : null,
            'venue' => $isEvent ? $data['venue'] : null,
            'is_pinned' => $request->boolean('is_pinned'),
            'show_in_ticker' => $request->boolean('show_in_ticker'),
        ]);

        if ($request->boolean('remove_cover') && $post->cover_path) {
            Storage::disk('public')->delete($post->cover_path);
            $post->cover_path = null;
        }
        if ($request->hasFile('cover')) {
            if ($post->cover_path) {
                Storage::disk('public')->delete($post->cover_path);
            }
            $post->cover_path = $request->file('cover')->store('posts/covers', 'public');
        }
        if ($request->boolean('remove_attachment') && $post->attachment_path) {
            Storage::disk('public')->delete($post->attachment_path);
            $post->attachment_path = null;
            $post->attachment_name = null;
        }
        if ($request->hasFile('attachment')) {
            if ($post->attachment_path) {
                Storage::disk('public')->delete($post->attachment_path);
            }
            $file = $request->file('attachment');
            $post->attachment_path = $file->store('posts/attachments', 'public');
            $post->attachment_name = ($data['attachment_name'] ?? null) ?: $file->getClientOriginalName();
        } elseif ($post->attachment_path && ! empty($data['attachment_name'])) {
            $post->attachment_name = $data['attachment_name'];
        }
    }

    private function savedMessage(Post $post): string
    {
        return match ($post->liveState()) {
            'live' => 'Saved — live on the website now.',
            'scheduled' => 'Scheduled — goes live '.$post->published_at->format('j M, g:i A').'.',
            'expired' => 'Saved, but the expiry date has passed so it is hidden.',
            default => 'Saved as a draft. Visitors cannot see it yet.',
        };
    }
}
