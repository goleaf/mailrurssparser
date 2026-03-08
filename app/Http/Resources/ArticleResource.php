<?php

namespace App\Http\Resources;

use App\Support\Utf8Normalizer;
use Illuminate\Http\Request;

class ArticleResource extends ArticleListResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $isShowRoute = $request->routeIs('*.show');

        return Utf8Normalizer::normalize(array_merge(parent::toArray($request), [
            'full_content' => $this->when(
                $isShowRoute,
                function (): string {
                    if (filled($this->full_description) && method_exists($this->resource, 'renderRichContent')) {
                        return (string) $this->resource->renderRichContent('full_description');
                    }

                    return (string) ($this->rss_content ?? '');
                },
            ),
            'meta_title' => $this->when($isShowRoute, $this->meta_title),
            'meta_description' => $this->when($isShowRoute, $this->meta_description),
            'structured_data' => $this->when($isShowRoute, $this->structured_data ?? $this->generateStructuredData()),
            'related_ids' => $this->when(
                $isShowRoute,
                fn (): array => $this->related_ids ?? [],
            ),
            'related_articles' => $this->when(
                $isShowRoute,
                fn (): array => $this->related_articles ?? [],
            ),
            'similar_articles' => $this->when(
                $isShowRoute,
                fn (): array => $this->similar_articles ?? [],
            ),
            'more_from_category' => $this->when(
                $isShowRoute,
                fn (): array => $this->more_from_category ?? [],
            ),
        ]));
    }
}
