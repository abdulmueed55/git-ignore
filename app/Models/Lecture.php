<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Lecture extends Model
{
    protected $fillable = [
        'course_id', 'timetable_slot_id', 'held_on', 'starts_at', 'topic', 'marked_by',
        'present_count', 'absent_count', 'late_count', 'leave_count', 'locked_at',
    ];

    protected function casts(): array
    {
        return ['held_on' => 'date', 'locked_at' => 'datetime'];
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function slot(): BelongsTo
    {
        return $this->belongsTo(TimetableSlot::class, 'timetable_slot_id');
    }

    public function marker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'marked_by');
    }

    public function records(): HasMany
    {
        return $this->hasMany(AttendanceRecord::class);
    }

    public function isLocked(): bool
    {
        return $this->locked_at !== null && $this->locked_at->isPast();
    }

    public function totalCount(): int
    {
        return $this->present_count + $this->absent_count + $this->late_count + $this->leave_count;
    }

    public function presentRate(): float
    {
        $total = $this->totalCount();

        return $total ? round(($this->present_count + $this->late_count) / $total * 100, 1) : 0.0;
    }
}
