<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ArticleSearchRequest;
use App\Http\Requests\Api\SearchHighlightsRequest;
use App\Http\Requests\Api\SearchSuggestRequest;
use App\Http\Resources\ArticleCollection;
use App\Models\Article;
use App\Models\Category;
use App\Models\Tag;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class SearchController extends Controller
{
    public function index(ArticleSearchRequest $request): ArticleCollection
    {
        $validated = $request->validated();
        $term = (string) $validated['q'];
        $perPage = (int) ($validated['per_page'] ?? 20);

        try {
            $articles = Article::search($term)
                ->query(function (Builder $query) use ($validated): void {
                    $query->published()->with(['category', 'tags']);
                    $this->applySearchFilters($query, $validated);
                    $this->applySearchSort($query, $validated['sort'] ?? 'relevance', $validated['q']);
                })
                ->paginate($perPage);

            if ($articles->total() === 0) {
                throw new \RuntimeException('Scout returned no results.');
            }
        } catch (\Throwable) {
            $articlesQuery = Article::query()
                ->published()
                ->with(['category', 'tags'])
                ->where(function (Builder $query) use ($term): void {
                    $like = '%'.$term.'%';

                    $query->where('title', 'like', $like)
                        ->orWhere('short_description', 'like', $like)
                        ->orWhere('full_description', 'like', $like)
                        ->orWhere('author', 'like', $like);
                });

            $this->applySearchFilters($articlesQuery, $validated);
            $this->applySearchSort($articlesQuery, $validated['sort'] ?? 'relevance', $term);

            $articles = $articlesQuery->paginate($perPage);
        }

        $suggestions = [];

        if ($articles->total() === 0) {
            $suggestions = [
                'categories' => Category::query()
                    ->active()
                    ->where('name', 'like', '%'.$term.'%')
                    ->limit(3)
                    ->get(['id', 'name', 'slug', 'color'])
                    ->toArray(),
                'tags' => Tag::query()
                    ->where('name', 'like', '%'.$term.'%')
                    ->orderByDesc('usage_count')
                    ->limit(3)
                    ->get(['id', 'name', 'slug', 'color'])
                    ->toArray(),
            ];
        }

        return (new ArticleCollection($articles))->additional([
            'meta' => [
                'query' => $term,
                'total' => $articles->total(),
                'suggestions' => $suggestions,
            ],
        ]);
    }

    public function suggest(SearchSuggestRequest $request): \Illuminate\Http\JsonResponse
    {
        $term = $request->validated('q');

        return response()->json([
            'articles' => Article::query()
                ->published()
                ->where('title', 'like', '%'.$term.'%')
                ->select('id', 'title', 'slug', 'published_at')
                ->limit(5)
                ->get(),
            'categories' => Category::query()
                ->active()
                ->where('name', 'like', '%'.$term.'%')
                ->select('id', 'name', 'slug', 'color')
                ->limit(3)
                ->get(),
            'tags' => Tag::query()
                ->where('name', 'like', '%'.$term.'%')
                ->select('id', 'name', 'slug')
                ->orderByDesc('usage_count')
                ->limit(5)
                ->get(),
        ]);
    }

    public function highlights(SearchHighlightsRequest $request): \Illuminate\Http\JsonResponse
    {
        $validated = $request->validated();
        $term = (string) $validated['q'];
        $article = Article::query()->findOrFail($validated['article_id']);
        $content = strip_tags((string) $article->content);
        $sentences = preg_split('/(?<=[.!?])\s+/u', $content) ?: [$content];
        $match = collect($sentences)
            ->first(fn (string $sentence): bool => stripos($sentence, $term) !== false) ?? Str::limit($content, 220);

        $escapedTerm = preg_quote($term, '/');
        $excerpt = preg_replace("/({$escapedTerm})/iu", '<mark>$1</mark>', $match) ?? $match;

        return response()->json(['excerpt' => $excerpt]);
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function applySearchFilters(Builder $query, array $validated): void
    {
        $query
            ->when($validated['category'] ?? null, fn (Builder $query, string $slug) => $query->byCategory($slug))
            ->when($validated['tag'] ?? null, fn (Builder $query, string $slug) => $query->byTag($slug))
            ->when($validated['content_type'] ?? null, fn (Builder $query, string $type) => $query->byContentType($type))
            ->when(($validated['date_from'] ?? null) && ($validated['date_to'] ?? null), fn (Builder $query) => $query->byDateRange($validated['date_from'], $validated['date_to']));
    }

    private function applySearchSort(Builder $query, string $sort, string $term): void
    {
        if ($sort === 'latest') {
            $query->orderByDesc('published_at');

            return;
        }

        if ($sort === 'popular') {
            $query->popular();

            return;
        }

        $escaped = str_replace(['%', '_'], ['\%', '\_'], $term);

        $query->orderByRaw(
            'CASE
                WHEN title = ? THEN 100
                WHEN title LIKE ? THEN 75
                WHEN title LIKE ? THEN 50
                WHEN short_description LIKE ? THEN 25
                ELSE 10
            END DESC',
            [$term, $term.'%', '%'.$escaped.'%', '%'.$escaped.'%'],
        )->orderByDesc('published_at');
    }
}
