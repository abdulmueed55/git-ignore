<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AdmissionCycle extends Model
{
    protected $fillable = ['academic_session_id', 'title', 'opens_at', 'closes_at', 'application_fee', 'program_ids', 'status', 'instructions'];

    protected function casts(): array
    {
        return ['opens_at' => 'datetime', 'closes_at' => 'datetime', 'program_ids' => 'array'];
    }

    public static function active(): ?self
    {
        return static::where('status', 'open')->latest('opens_at')->first();
    }

    public function isAcceptingApplications(): bool
    {
        return $this->status === 'open' && now()->between($this->opens_at, $this->closes_at);
    }

    /** Programs applicants may choose in this cycle. */
    public function programs()
    {
        return Program::where('is_active', true)
            ->when($this->program_ids, fn ($q) => $q->whereIn('id', $this->program_ids))
            ->orderBy('level')->orderBy('name')->get();
    }

    public function daysLeft(): int
    {
        return (int) max(0, ceil(now()->diffInHours($this->closes_at, false) / 24));
    }

    public function stateLabel(): string
    {
        return match (true) {
            $this->status !== 'open' => ucfirst($this->status),
            now()->lt($this->opens_at) => 'Opens soon',
            now()->gt($this->closes_at) => 'Deadline passed',
            default => 'Open',
        };
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
