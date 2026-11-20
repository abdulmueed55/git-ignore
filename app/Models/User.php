<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    public const ROLES = [
        'admin' => 'Administrator',
        'principal' => 'Principal',
        'admission_officer' => 'Admission Officer',
        'teacher' => 'Teacher',
        'editor' => 'Website Editor',
    ];

    protected $fillable = [
        'name', 'email', 'password', 'role', 'phone', 'designation', 'department_id',
        'employee_no', 'avatar_path', 'is_active', 'last_login_at',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function courses(): HasMany
    {
        return $this->hasMany(Course::class, 'teacher_id');
    }

    public function hasRole(string ...$roles): bool
    {
        return in_array($this->role, $roles, true);
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    /** Admin & principal can see everything. */
    public function isLeadership(): bool
    {
        return in_array($this->role, ['admin', 'principal'], true);
    }

    public function roleLabel(): string
    {
        return self::ROLES[$this->role] ?? ucfirst($this->role);
    }

    public function initials(): string
    {
        $parts = preg_split('/\s+/', trim(preg_replace('/^((Prof|Dr|Mr|Ms|Mrs|Hafiz)\.?\s+)+/i', '', $this->name)));

        return strtoupper(mb_substr($parts[0] ?? '', 0, 1).mb_substr(end($parts) ?: '', 0, 1));
    }
}
