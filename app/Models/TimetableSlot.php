<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TimetableSlot extends Model
{
    public const DAYS = [1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday'];

    protected $fillable = ['course_id', 'weekday', 'starts_at', 'ends_at', 'room'];

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }
}
