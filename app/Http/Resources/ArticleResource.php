<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ArticleResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'slug' => $this->slug,
            'short_description' => $this->short_description,
            'image_url' => $this->image_url,
            'source_url' => $this->source_url,
            'author' => $this->author,
            'source_name' => $this->source_name,
            'status' => $this->status,
            'is_featured' => $this->is_featured,
            'is_breaking' => $this->is_breaking,
            'views_count' => $this->views_count,
            'reading_time' => $this->reading_time,
            'reading_time_text' => $this->reading_time.' мин чтения',
            'published_at' => $this->published_at?->toIso8601String(),
            'published_at_human' => $this->published_at?->diffForHumans(),
            'category' => [
                'id' => $this->category->id,
                'name' => $this->category->name,
                'slug' => $this->category->slug,
                'color' => $this->category->color,
                'icon' => $this->category->icon,
            ],
            'sub_category' => $this->whenLoaded('subCategory', fn () => [
                'id' => $this->subCategory->id,
                'name' => $this->subCategory->name,
                'slug' => $this->subCategory->slug,
            ]),
            'tags' => $this->whenLoaded('tags', fn () => $this->tags->map(fn ($tag) => [
                'id' => $tag->id,
                'name' => $tag->name,
                'slug' => $tag->slug,
                'color' => $tag->color,
            ])),
            'full_description' => $this->when(
                $request->routeIs('api.articles.show'),
                $this->full_description ?? $this->rss_content,
            ),
        ];
    }
}
