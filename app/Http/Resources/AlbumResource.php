<?php

namespace App\Http\Resources;

use App\Models\GalleryAlbum;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin GalleryAlbum */
class AlbumResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $cover = $this->images->first();

        return [
            'title' => $this->title,
            'slug' => $this->slug,
            'description' => $this->description,
            'taken_on' => $this->taken_on?->toDateString(),
            'cover_url' => $cover ? url($cover->url()) : null,
            'image_count' => $this->images->count(),
            'images' => $this->when($request->routeIs('api.gallery.show'), fn () => $this->images->map(fn ($i) => [
                'url' => url($i->url()),
                'caption' => $i->caption,
            ])),
        ];
    }
}
