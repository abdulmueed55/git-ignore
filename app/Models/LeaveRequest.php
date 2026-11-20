<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeaveRequest extends Model
{
    protected $fillable = ['student_id', 'from_date', 'to_date', 'reason', 'status', 'decided_by'];

    protected function casts(): array
    {
        return ['from_date' => 'date', 'to_date' => 'date'];
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
