<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GuardianAlert extends Model
{
    protected $fillable = ['student_id', 'channel', 'kind', 'to', 'message', 'status', 'sent_at'];

    protected function casts(): array
    {
        return ['sent_at' => 'datetime'];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }
}
