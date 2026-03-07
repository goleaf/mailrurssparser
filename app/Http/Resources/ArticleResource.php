<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

class ArticleResource extends ArticleListResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return array_merge(parent::toArray($request), [
            'full_content' => $this->when(
                $request->routeIs('api.v1.articles.show'),
                fn (): string => (string) ($this->full_description ?? $this->rss_content),
            ),
            'meta_title' => $this->when($request->routeIs('api.v1.articles.show'), $this->meta_title),
            'meta_description' => $this->when($request->routeIs('api.v1.articles.show'), $this->meta_description),
            'structured_data' => $this->when($request->routeIs('api.v1.articles.show'), $this->structured_data ?? $this->generateStructuredData()),
            'related_ids' => $this->when(
                $request->routeIs('api.v1.articles.show'),
                fn (): array => $this->related_ids ?? [],
            ),
        ]);
    }
}
