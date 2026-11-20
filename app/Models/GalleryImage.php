<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GalleryImage extends Model
{
    protected $fillable = ['gallery_album_id', 'path', 'caption', 'sort_order'];

    public function album(): BelongsTo
    {
        return $this->belongsTo(GalleryAlbum::class, 'gallery_album_id');
    }
}
