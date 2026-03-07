<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ArticleListResource extends JsonResource
{
    /**
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
            'image_caption' => $this->image_caption,
            'source_url' => $this->source_url,
            'author' => $this->author,
            'source_name' => $this->source_name,
            'status' => $this->status,
            'content_type' => $this->content_type,
            'is_featured' => $this->is_featured,
            'is_breaking' => $this->is_breaking,
            'is_pinned' => $this->is_pinned,
            'is_editors_choice' => $this->is_editors_choice,
            'is_sponsored' => $this->is_sponsored,
            'importance' => $this->importance,
            'views_count' => $this->views_count,
            'shares_count' => $this->shares_count,
            'bookmarks_count' => $this->bookmarks_count,
            'reading_time' => $this->reading_time,
            'reading_time_text' => "{$this->reading_time} мин чтения",
            'published_at' => $this->published_at?->toIso8601String(),
            'published_at_human' => $this->published_at?->locale('ru')->diffForHumans(),
            'published_at_date' => $this->published_at?->locale('ru')->translatedFormat('d M Y'),
            'is_recent' => $this->is_recent,
            'category' => [
                'id' => $this->category?->id,
                'name' => $this->category?->name,
                'slug' => $this->category?->slug,
                'color' => $this->category?->color,
                'icon' => $this->category?->icon,
            ],
            'sub_category' => $this->whenLoaded('subCategory', function (): ?array {
                if ($this->subCategory === null) {
                    return null;
                }

                return [
                    'id' => $this->subCategory->id,
                    'name' => $this->subCategory->name,
                    'slug' => $this->subCategory->slug,
                ];
            }),
            'tags' => $this->whenLoaded('tags', function () {
                return $this->tags->map(fn ($tag): array => [
                    'id' => $tag->id,
                    'name' => $tag->name,
                    'slug' => $tag->slug,
                    'color' => $tag->color,
                ])->values()->all();
            }),
        ];
    }
}
