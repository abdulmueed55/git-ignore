<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Casts\Attribute;
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
        return ['locked_at' => 'datetime'];
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

    /** Stored as a plain Y-m-d string so date comparisons behave the same on SQLite and MySQL. */
    protected function heldOn(): Attribute
    {
        return Attribute::make(
            get: fn ($v) => $v ? Carbon::parse($v)->startOfDay() : null,
            set: fn ($v) => $v ? Carbon::parse($v)->toDateString() : null,
        );
    }

    /** Recalculate the denormalised P/A/L/E counters from the records. */
    public function refreshCounts(): void
    {
        $c = $this->records()->selectRaw('status, count(*) n')->groupBy('status')->pluck('n', 'status');
        $this->forceFill([
            'present_count' => (int) ($c['P'] ?? 0),
            'absent_count' => (int) ($c['A'] ?? 0),
            'late_count' => (int) ($c['L'] ?? 0),
            'leave_count' => (int) ($c['E'] ?? 0),
        ])->save();
    }

    /** Percentage used everywhere: (P + L) / (P + L + A); approved leave is excluded. */
    public function attendanceRate(): ?float
    {
        $den = $this->present_count + $this->late_count + $this->absent_count;

        return $den ? round(($this->present_count + $this->late_count) / $den * 100, 1) : null;
    }
}
