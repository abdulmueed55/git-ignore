<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MeritList extends Model
{
    protected $fillable = ['admission_cycle_id', 'program_id', 'list_no', 'closing_merit', 'seats_offered', 'status', 'published_at', 'fee_deadline'];

    protected function casts(): array
    {
        return ['published_at' => 'datetime', 'fee_deadline' => 'date', 'closing_merit' => 'float'];
    }

    public function cycle(): BelongsTo
    {
        return $this->belongsTo(AdmissionCycle::class, 'admission_cycle_id');
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }

    public function entries(): HasMany
    {
        return $this->hasMany(MeritListEntry::class)->orderBy('position');
    }
}
