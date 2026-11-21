<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A one-off class outside the weekly timetable (extra, make-up or class test). */
class ExtraClass extends Model
{
    public const KINDS = ['extra' => 'Extra class', 'makeup' => 'Make-up class', 'test' => 'Class test'];

    protected $fillable = ['course_id', 'held_on', 'starts_at', 'ends_at', 'room', 'kind', 'reason', 'students_notified', 'created_by', 'cancelled_at', 'cancelled_by'];

    protected function casts(): array
    {
        return ['held_on' => 'date', 'students_notified' => 'boolean', 'cancelled_at' => 'datetime'];
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->whereNull('cancelled_at');
    }

    public function kindLabel(): string
    {
        return self::KINDS[$this->kind] ?? 'Extra class';
    }

    public function start(): Carbon
    {
        return Carbon::parse($this->held_on->toDateString().' '.$this->starts_at);
    }

    public function end(): Carbon
    {
        return Carbon::parse($this->held_on->toDateString().' '.$this->ends_at);
    }

    public function isPast(): bool
    {
        return $this->end()->isPast();
    }
}
