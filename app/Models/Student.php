<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Student extends Model
{
    protected $fillable = [
        'user_id', 'program_id', 'section_id', 'academic_session_id', 'admission_application_id',
        'roll_no', 'registration_no', 'name', 'father_name', 'cnic', 'father_cnic', 'date_of_birth',
        'gender', 'phone', 'guardian_phone', 'email', 'address', 'city', 'domicile', 'blood_group',
        'photo_path', 'status',
    ];

    protected function casts(): array
    {
        return ['date_of_birth' => 'date'];
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }

    public function academicSession(): BelongsTo
    {
        return $this->belongsTo(AcademicSession::class);
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(AdmissionApplication::class, 'admission_application_id');
    }

    public function attendance(): HasMany
    {
        return $this->hasMany(AttendanceRecord::class);
    }

    public function alerts(): HasMany
    {
        return $this->hasMany(GuardianAlert::class);
    }

    public function leaveRequests(): HasMany
    {
        return $this->hasMany(LeaveRequest::class);
    }

    public function initials(): string
    {
        $parts = preg_split('/\s+/', trim($this->name));

        return strtoupper(mb_substr($parts[0] ?? '', 0, 1).mb_substr(end($parts) ?: '', 0, 1));
    }

    /** Teachers only see students of sections they teach; everyone else sees all. */
    public function scopeVisibleTo(Builder $q, User $user): Builder
    {
        if ($user->role !== 'teacher') {
            return $q;
        }

        return $q->whereIn('section_id', Course::where('teacher_id', $user->id)->select('section_id'));
    }

    public function scopeSearch(Builder $q, ?string $term): Builder
    {
        $term = trim((string) $term);
        if ($term === '') {
            return $q;
        }

        return $q->where(fn ($w) => $w->where('name', 'like', "%{$term}%")
            ->orWhere('roll_no', 'like', "%{$term}%")
            ->orWhere('cnic', 'like', "%{$term}%")
            ->orWhere('father_name', 'like', "%{$term}%"));
    }

    /** Overall attendance (present + late counted as attended), null when no records. */
    public function attendanceSummary(): ?array
    {
        $rows = $this->attendance()->selectRaw('status, count(*) c')->groupBy('status')->pluck('c', 'status');
        $total = (int) $rows->sum();
        if ($total === 0) {
            return null;
        }

        return [
            'total' => $total,
            'present' => (int) ($rows['P'] ?? 0),
            'absent' => (int) ($rows['A'] ?? 0),
            'late' => (int) ($rows['L'] ?? 0),
            'leave' => (int) ($rows['E'] ?? 0),
            'percentage' => round((($rows['P'] ?? 0) + ($rows['L'] ?? 0)) / $total * 100, 1),
        ];
    }
}
