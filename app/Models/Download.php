<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Download extends Model
{
    public const CATEGORIES = ['prospectus' => 'Prospectus', 'forms' => 'Forms', 'datesheets' => 'Date sheets', 'results' => 'Results', 'syllabus' => 'Syllabus', 'policies' => 'Policies'];

    protected $fillable = ['title', 'category', 'file_path', 'file_name', 'size', 'download_count', 'is_published'];

    protected function casts(): array
    {
        return ['is_published' => 'boolean'];
    }
}
