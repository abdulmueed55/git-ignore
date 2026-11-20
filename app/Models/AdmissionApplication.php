<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

class AdmissionApplication extends Model
{
    public const STATUSES = [
        'draft' => 'Draft',
        'submitted' => 'Submitted',
        'under_review' => 'Under review',
        'verified' => 'Verified',
        'flagged' => 'Flagged',
        'selected' => 'Selected',
        'admitted' => 'Admitted',
        'waitlisted' => 'Waitlisted',
        'rejected' => 'Rejected',
    ];

    public const QUOTAS = [
        'open' => 'Open merit',
        'sports' => 'Sports',
        'hafiz' => 'Hafiz-e-Quran',
        'disabled' => 'Special persons',
        'fg_employee' => 'FG employee child',
        'army' => 'Armed forces',
    ];

    public const DOCUMENT_TYPES = [
        'cnic' => 'Applicant CNIC / B-Form',
        'father_cnic' => "Father's CNIC",
        'matric_dmc' => 'Matric (SSC) result card',
        'inter_dmc' => 'Intermediate (HSSC) result card',
        'photo' => 'Passport photograph',
        'domicile' => 'Domicile certificate',
        'character_certificate' => 'Character certificate',
    ];

    protected $fillable = [
        'admission_cycle_id', 'program_id', 'second_program_id', 'tracking_no', 'access_pin',
        'name', 'father_name', 'cnic', 'father_cnic', 'date_of_birth', 'gender', 'religion', 'phone',
        'guardian_phone', 'email', 'address', 'city', 'domicile', 'quota', 'is_hafiz',
        'matric_board', 'matric_roll_no', 'matric_year', 'matric_obtained', 'matric_total', 'matric_group',
        'inter_board', 'inter_roll_no', 'inter_year', 'inter_obtained', 'inter_total', 'inter_group',
        'merit_score', 'verification_score', 'verification_status', 'autofilled_fields',
        'status', 'remarks', 'reviewed_by', 'submitted_at', 'reviewed_at',
    ];

    protected $hidden = ['access_pin'];

    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'is_hafiz' => 'boolean',
            'autofilled_fields' => 'array',
            'merit_score' => 'float',
            'submitted_at' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }

    public static function newTrackingNo(): string
    {
        do {
            $no = 'FGLA-'.now()->format('y').'-'.strtoupper(Str::random(5));
        } while (static::where('tracking_no', $no)->exists());

        return $no;
    }

    public function cycle(): BelongsTo
    {
        return $this->belongsTo(AdmissionCycle::class, 'admission_cycle_id');
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }

    public function secondProgram(): BelongsTo
    {
        return $this->belongsTo(Program::class, 'second_program_id');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(ApplicationDocument::class);
    }

    public function checks(): HasMany
    {
        return $this->hasMany(VerificationCheck::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function student(): HasOne
    {
        return $this->hasOne(Student::class);
    }

    public function document(string $type): ?ApplicationDocument
    {
        return $this->documents->firstWhere('type', $type);
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? ucfirst($this->status);
    }

    public function matricPercentage(): ?float
    {
        return $this->matric_total ? round($this->matric_obtained / $this->matric_total * 100, 2) : null;
    }

    public function interPercentage(): ?float
    {
        return $this->inter_total ? round($this->inter_obtained / $this->inter_total * 100, 2) : null;
    }

    /** Documents an applicant must upload for the chosen program. */
    public function requiredDocumentTypes(): array
    {
        $types = ['cnic', 'father_cnic', 'matric_dmc', 'photo', 'domicile'];
        if ($this->program?->basedOnInter()) {
            $types[] = 'inter_dmc';
        }

        return $types;
    }
}
