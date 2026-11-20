<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApplicationDocument extends Model
{
    protected $fillable = [
        'admission_application_id', 'type', 'path', 'original_name', 'mime', 'size',
        'extraction_status', 'extracted', 'confidence', 'extraction_error', 'extracted_by', 'extracted_at',
    ];

    protected function casts(): array
    {
        return ['extracted' => 'array', 'extracted_at' => 'datetime'];
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(AdmissionApplication::class, 'admission_application_id');
    }

    public function label(): string
    {
        return AdmissionApplication::DOCUMENT_TYPES[$this->type] ?? $this->type;
    }

    public function isImage(): bool
    {
        return str_starts_with($this->mime, 'image/');
    }

    /** Which document types carry data the AI should read (photo is visual only). */
    public function isExtractable(): bool
    {
        return $this->type !== 'photo';
    }
}
