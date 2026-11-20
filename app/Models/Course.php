<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A subject taught to one section by one teacher. */
class Course extends Model
{
    protected $fillable = ['section_id', 'subject_id', 'teacher_id'];

    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    public function slots(): HasMany
    {
        return $this->hasMany(TimetableSlot::class);
    }

    public function lectures(): HasMany
    {
        return $this->hasMany(Lecture::class);
    }

    public function label(): string
    {
        return $this->subject?->name.' — '.$this->section?->label();
    }
}
