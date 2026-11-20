<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AdmissionCycle extends Model
{
    protected $fillable = ['academic_session_id', 'title', 'opens_at', 'closes_at', 'application_fee', 'status', 'instructions'];

    protected function casts(): array
    {
        return ['opens_at' => 'datetime', 'closes_at' => 'datetime'];
    }

    public static function active(): ?self
    {
        return static::where('status', 'open')->latest('opens_at')->first();
    }

    public function isAcceptingApplications(): bool
    {
        return $this->status === 'open' && now()->between($this->opens_at, $this->closes_at);
    }

    public function academicSession(): BelongsTo
    {
        return $this->belongsTo(AcademicSession::class);
    }

    public function applications(): HasMany
    {
        return $this->hasMany(AdmissionApplication::class);
    }

    public function meritLists(): HasMany
    {
        return $this->hasMany(MeritList::class);
    }
}
