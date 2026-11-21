<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeaveRequest extends Model
{
    protected $fillable = ['student_id', 'filed_by', 'from_date', 'to_date', 'reason', 'status', 'decided_by'];

    protected function fromDate(): Attribute
    {
        return Attribute::make(get: fn ($v) => $v ? Carbon::parse($v)->startOfDay() : null, set: fn ($v) => $v ? Carbon::parse($v)->toDateString() : null);
    }

    protected function toDate(): Attribute
    {
        return Attribute::make(get: fn ($v) => $v ? Carbon::parse($v)->startOfDay() : null, set: fn ($v) => $v ? Carbon::parse($v)->toDateString() : null);
    }

    public function days(): int
    {
        return (int) $this->from_date->diffInDays($this->to_date) + 1;
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }
}
