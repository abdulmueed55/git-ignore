<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Post extends Model
{
    public const TYPES = ['announcement' => 'Announcement', 'news' => 'News', 'event' => 'Event', 'notice' => 'Notice'];

    public const STATUSES = ['draft' => 'Draft', 'scheduled' => 'Scheduled', 'published' => 'Published', 'archived' => 'Archived'];

    protected $fillable = [
        'type', 'title', 'slug', 'excerpt', 'body', 'category', 'cover_path', 'attachment_path', 'attachment_name',
        'event_starts_at', 'event_ends_at', 'venue', 'is_pinned', 'show_in_ticker', 'status',
        'published_at', 'expires_at', 'views', 'author_id',
    ];

    protected function casts(): array
    {
        return [
            'event_starts_at' => 'datetime',
            'event_ends_at' => 'datetime',
            'published_at' => 'datetime',
            'expires_at' => 'datetime',
            'is_pinned' => 'boolean',
            'show_in_ticker' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Post $post) {
            if (blank($post->slug)) {
                $base = Str::slug($post->title) ?: 'post';
                $slug = $base;
                $i = 2;
                while (static::where('slug', $slug)->where('id', '!=', $post->id)->exists()) {
                    $slug = $base.'-'.$i++;
                }
                $post->slug = $slug;
            }
        });
    }

    /** Visible on the public website right now. */
    public function scopeLive(Builder $q): Builder
    {
        return $q->whereIn('status', ['published', 'scheduled'])
            ->where('published_at', '<=', now())
            ->where(fn ($w) => $w->whereNull('expires_at')->orWhere('expires_at', '>', now()));
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function isLive(): bool
    {
        return in_array($this->status, ['published', 'scheduled'], true)
            && $this->published_at?->isPast()
            && (! $this->expires_at || $this->expires_at->isFuture());
    }

    /** Effective status as a visitor would experience it. */
    public function liveState(): string
    {
        if ($this->status === 'draft' || $this->status === 'archived') {
            return $this->status;
        }
        if ($this->published_at?->isFuture()) {
            return 'scheduled';
        }
        if ($this->expires_at?->isPast()) {
            return 'expired';
        }

        return 'live';
    }
}
