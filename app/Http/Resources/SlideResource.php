<?php

namespace App\Http\Resources;

use App\Models\HeroSlide;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin HeroSlide */
class SlideResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'heading' => $this->heading,
            'subheading' => $this->subheading,
            'image_url' => url($this->imageUrl()),
            'cta' => $this->cta_label ? ['label' => $this->cta_label, 'url' => $this->cta_url] : null,
        ];
    }
}
