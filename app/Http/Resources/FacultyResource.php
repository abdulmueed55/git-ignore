<?php

namespace App\Http\Resources;

use App\Models\FacultyProfile;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin FacultyProfile */
class FacultyResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'name' => $this->name,
            'designation' => $this->designation,
            'department' => $this->department?->name,
            'qualification' => $this->qualification,
            'specialization' => $this->specialization,
            'email' => $this->email,
            'photo_url' => $this->photoUrl() ? url($this->photoUrl()) : null,
            'bio' => $this->bio,
        ];
    }
}
