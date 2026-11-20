<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Program extends Model
{
    public const LEVELS = ['intermediate' => 'Intermediate (HSSC)', 'adp' => 'Associate Degree (ADP)', 'bs' => 'BS (4-Year)'];

    protected $fillable = [
        'department_id', 'name', 'code', 'level', 'duration_years', 'seats', 'shift',
        'eligibility', 'min_percentage', 'merit_weights', 'annual_fee', 'is_active',
    ];

    protected function casts(): array
    {
        return ['merit_weights' => 'array', 'is_active' => 'boolean', 'min_percentage' => 'float'];
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function sections(): HasMany
    {
        return $this->hasMany(Section::class);
    }

    public function students(): HasMany
    {
        return $this->hasMany(Student::class);
    }

    public function applications(): HasMany
    {
        return $this->hasMany(AdmissionApplication::class);
    }

    /** Intermediate programs are admitted on matric marks, degree programs on inter marks. */
    public function basedOnInter(): bool
    {
        return $this->level !== 'intermediate';
    }

    /** The only class this programme admits into: 1st year for intermediate, the first semester for degrees. */
    public function admissionClass(): string
    {
        return $this->level === 'intermediate' ? '1st Year (HSSC Part-I)' : '1st Semester';
    }

    public function weights(): array
    {
        return array_merge(['academic' => 1.0, 'hafiz_bonus' => 20], $this->merit_weights ?? []);
    }

    public function levelLabel(): string
    {
        return self::LEVELS[$this->level] ?? strtoupper($this->level);
    }
}
