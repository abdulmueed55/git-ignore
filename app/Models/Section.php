<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Section extends Model
{
    protected $fillable = ['program_id', 'academic_session_id', 'name', 'year', 'room', 'incharge_id', 'capacity'];

    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }

    public function academicSession(): BelongsTo
    {
        return $this->belongsTo(AcademicSession::class);
    }

    public function incharge(): BelongsTo
    {
        return $this->belongsTo(User::class, 'incharge_id');
    }

    public function students(): HasMany
    {
        return $this->hasMany(Student::class);
    }

    public function courses(): HasMany
    {
        return $this->hasMany(Course::class);
    }

    public function label(): string
    {
        return $this->program?->code.' · '.$this->name;
    }
}
