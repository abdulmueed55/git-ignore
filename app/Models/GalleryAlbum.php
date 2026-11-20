<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GalleryAlbum extends Model
{
    protected $fillable = ['title', 'slug', 'description', 'taken_on', 'is_published'];

    protected function casts(): array
    {
        return ['taken_on' => 'date', 'is_published' => 'boolean'];
    }

    public function images(): HasMany
    {
        return $this->hasMany(GalleryImage::class)->orderBy('sort_order');
    }
}
