<?php

namespace App\Http\Resources;

use App\Models\FacultyProfile;
use App\Models\User;
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
            // A staff sign-in address is never published (it would hand out usernames for password guessing).
            'email' => $this->email && ! User::where('email', $this->email)->exists() ? $this->email : null,
            'photo_url' => $this->photoUrl() ? url($this->photoUrl()) : null,
            'bio' => $this->bio,
        ];
    }
}
