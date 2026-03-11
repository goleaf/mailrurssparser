<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TagResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'color' => $this->color,
            'seo' => $this->resource->getSeoData(),
            'usage_count' => $this->usage_count,
            'is_trending' => $this->is_trending,
            'is_featured' => $this->is_featured,
        ];
    }
}
