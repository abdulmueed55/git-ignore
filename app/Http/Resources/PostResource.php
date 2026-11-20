<?php

namespace App\Http\Resources;

use App\Models\Post;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Post */
class PostResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $full = (bool) ($this->additional['full'] ?? false) || $request->routeIs('api.posts.show');

        return [
            'id' => $this->id,
            'type' => $this->type,
            'title' => $this->title,
            'slug' => $this->slug,
            'excerpt' => $this->excerpt,
            'body_html' => $this->when($full, $this->body),
            'category' => $this->category,
            'cover_url' => $this->coverUrl() ? url($this->coverUrl()) : null,
            'attachment' => $this->attachment_path ? ['name' => $this->attachment_name, 'url' => url($this->attachmentUrl())] : null,
            'event' => $this->type === 'event' ? [
                'starts_at' => $this->event_starts_at?->toIso8601String(),
                'ends_at' => $this->event_ends_at?->toIso8601String(),
                'venue' => $this->venue,
            ] : null,
            'is_pinned' => $this->is_pinned,
            'published_at' => $this->published_at?->toIso8601String(),
            'expires_at' => $this->expires_at?->toIso8601String(),
            'views' => $this->views,
            'url' => route('portal.post', $this->slug),
        ];
    }
}
