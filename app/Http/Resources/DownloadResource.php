<?php

namespace App\Http\Resources;

use App\Models\Download;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Download */
class DownloadResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'title' => $this->title,
            'category' => $this->category,
            'category_label' => $this->categoryLabel(),
            'file_name' => $this->file_name,
            'size' => $this->sizeLabel(),
            'url' => route('portal.download', $this->id),
            'updated_at' => $this->updated_at?->toDateString(),
        ];
    }
}
