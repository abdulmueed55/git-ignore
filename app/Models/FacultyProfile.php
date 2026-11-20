<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FacultyProfile extends Model
{
    protected $fillable = ['user_id', 'department_id', 'name', 'designation', 'qualification', 'specialization', 'email', 'photo_path', 'bio', 'sort_order', 'is_published'];

    protected function casts(): array
    {
        return ['is_published' => 'boolean'];
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
