<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VerificationCheck extends Model
{
    protected $fillable = [
        'admission_application_id', 'field', 'label', 'form_value', 'document_values',
        'result', 'similarity', 'note', 'overridden', 'overridden_by',
    ];

    protected function casts(): array
    {
        return ['document_values' => 'array', 'overridden' => 'boolean'];
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(AdmissionApplication::class, 'admission_application_id');
    }
}
