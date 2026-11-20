<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Download extends Model
{
    public const CATEGORIES = ['prospectus' => 'Prospectus', 'forms' => 'Forms', 'datesheets' => 'Date sheets', 'results' => 'Results', 'syllabus' => 'Syllabus', 'policies' => 'Policies'];

    protected $fillable = ['title', 'category', 'file_path', 'file_name', 'size', 'download_count', 'is_published'];

    protected function casts(): array
    {
        return ['is_published' => 'boolean'];
    }

    public function url(): string
    {
        return Storage::disk('public')->url($this->file_path);
    }

    public function sizeLabel(): string
    {
        return $this->size >= 1048576 ? round($this->size / 1048576, 1).' MB' : max(1, round($this->size / 1024)).' KB';
    }

    public function categoryLabel(): string
    {
        return self::CATEGORIES[$this->category] ?? ucfirst($this->category);
    }
}
