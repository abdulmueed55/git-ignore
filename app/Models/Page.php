<?php

namespace App\Models;

use App\Http\Controllers\Studio\Concerns\HtmlSanitizer;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Page extends Model
{
    protected $fillable = ['title', 'slug', 'summary', 'body', 'meta_description', 'in_menu', 'menu_order', 'is_published', 'updated_by'];

    protected function casts(): array
    {
        return ['in_menu' => 'boolean', 'is_published' => 'boolean'];
    }

    /** Sanitised on every write, whatever the source (studio, seeder, importer, tinker). */
    protected function body(): Attribute
    {
        return Attribute::make(set: fn ($v) => $v === null ? null : HtmlSanitizer::clean($v));
    }

    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
