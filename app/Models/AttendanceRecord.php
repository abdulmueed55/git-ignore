<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceRecord extends Model
{
    public const STATUSES = ['P' => 'Present', 'A' => 'Absent', 'L' => 'Late', 'E' => 'Leave'];

    protected $fillable = ['lecture_id', 'student_id', 'status', 'remarks'];

    public function lecture(): BelongsTo
    {
        return $this->belongsTo(Lecture::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }
}
