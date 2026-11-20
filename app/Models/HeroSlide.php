<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HeroSlide extends Model
{
    protected $fillable = ['heading', 'subheading', 'image_path', 'cta_label', 'cta_url', 'sort_order', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
