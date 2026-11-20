<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ActivityLog extends Model
{
    protected $fillable = ['user_id', 'action', 'description', 'subject_type', 'subject_id', 'properties', 'ip'];

    protected function casts(): array
    {
        return ['properties' => 'array'];
    }

    /** Record an audit-trail entry for the current user. */
    public static function record(string $action, string $description, ?Model $subject = null, array $properties = []): self
    {
        return static::create([
            'user_id' => auth()->id(),
            'action' => $action,
            'description' => $description,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'properties' => $properties ?: null,
            'ip' => request()?->ip(),
        ]);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }
}
