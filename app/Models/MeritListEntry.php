<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MeritListEntry extends Model
{
    protected $fillable = ['merit_list_id', 'admission_application_id', 'position', 'merit_score'];

    public function meritList(): BelongsTo
    {
        return $this->belongsTo(MeritList::class);
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(AdmissionApplication::class, 'admission_application_id');
    }
}
