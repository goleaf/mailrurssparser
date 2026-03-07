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
        $isShowRoute = $request->routeIs('*.show');

        return array_merge(parent::toArray($request), [
            'full_content' => $this->when(
                $isShowRoute,
                fn (): string => (string) ($this->full_description ?? $this->rss_content),
            ),
            'meta_title' => $this->when($isShowRoute, $this->meta_title),
            'meta_description' => $this->when($isShowRoute, $this->meta_description),
            'structured_data' => $this->when($isShowRoute, $this->structured_data ?? $this->generateStructuredData()),
            'related_ids' => $this->when(
                $isShowRoute,
                fn (): array => $this->related_ids ?? [],
            ),
        ]);
    }
}
