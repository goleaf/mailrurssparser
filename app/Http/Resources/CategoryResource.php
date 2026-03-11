<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CategoryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $articlesCount = $this->resource->articles_count ?? $this->articles_count_cache ?? 0;

        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'color' => $this->color,
            'icon' => $this->icon,
            'description' => $this->description,
            'seo' => $this->resource->getSeoData(),
            'articles_count_cache' => (int) $articlesCount,
            'sub_categories' => $this->whenLoaded('subCategories', function () {
                return $this->subCategories->map(fn ($subCategory): array => [
                    'id' => $subCategory->id,
                    'name' => $subCategory->name,
                    'slug' => $subCategory->slug,
                ])->values()->all();
            }),
        ];
    }
}
