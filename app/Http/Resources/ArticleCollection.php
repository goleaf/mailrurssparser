<?php

namespace App\Http\Resources;

use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class ArticleCollection extends ResourceCollection
{
    /**
     * @var array<string, mixed>
     */
    protected array $extraMeta = [];

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'data' => $this->collection
                ->map(fn ($article): array => (new ArticleListResource($article))->toArray($request))
                ->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    public function extraMeta(array $meta): static
    {
        $this->extraMeta = $meta;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function with(Request $request): array
    {
        $meta = [
            'categories_summary' => Category::query()
                ->where('is_active', true)
                ->withCount(['articles' => fn ($query) => $query->published()])
                ->orderByDesc('articles_count')
                ->limit(5)
                ->get()
                ->map(fn (Category $category): array => [
                    'id' => $category->id,
                    'name' => $category->name,
                    'slug' => $category->slug,
                    'count' => $category->articles_count,
                ])
                ->all(),
            'total_results' => method_exists($this->resource, 'total')
                ? $this->resource->total()
                : $this->collection->count(),
        ];

        return [
            'meta' => array_merge($meta, $this->extraMeta),
        ];
    }
}
