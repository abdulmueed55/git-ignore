<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Page extends Model
{
    protected $fillable = ['title', 'slug', 'summary', 'body', 'meta_description', 'in_menu', 'menu_order', 'is_published', 'updated_by'];

    protected function casts(): array
    {
        return ['in_menu' => 'boolean', 'is_published' => 'boolean'];
    }

    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
