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
        $term = $this->normalizeUtf8((string) $validated['q']);
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

        $articles->appends($request->except('page'));

        $suggestions = [];

        if ($articles->total() === 0) {
            $categorySuggestions = collect($this->categorySuggestions($term, 3))
                ->map(fn (array $category): array => ['type' => 'category', ...$category]);

            $tagSuggestions = collect($this->tagSuggestions($term, 3))
                ->map(fn (array $tag): array => ['type' => 'tag', ...$tag]);

            $suggestions = $categorySuggestions
                ->concat($tagSuggestions)
                ->take(3)
                ->values()
                ->all();
        }

        return (new ArticleCollection($articles))->extraMeta([
            'query' => $term,
            'suggestions' => $suggestions,
        ]);
    }

    public function suggest(SearchSuggestRequest $request): \Illuminate\Http\JsonResponse
    {
        $term = $this->normalizeUtf8((string) $request->validated('q'));

        return response()->json([
            'articles' => $this->articleSuggestions($term),
            'categories' => $this->categorySuggestions($term, 3),
            'tags' => $this->tagSuggestions($term, 5),
        ]);
    }

    public function highlights(SearchHighlightsRequest $request): \Illuminate\Http\JsonResponse
    {
        $validated = $request->validated();
        $term = $this->normalizeUtf8((string) $validated['q']);
        $article = Article::query()->findOrFail($validated['article_id']);
        $content = $this->normalizeUtf8(strip_tags((string) $article->content));
        $sentences = preg_split('/(?<=[.!?])\s+/u', $content) ?: [$content];
        $match = collect($sentences)
            ->first(fn (string $sentence): bool => $this->containsTerm($sentence, $term));

        if ($match === null) {
            $match = $this->containsTerm($content, $term) ? $content : Str::limit($content, 220);
        }

        $excerpt = $this->highlightTerm($match, $term);

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
                WHEN short_description LIKE ? THEN 30
                WHEN full_description LIKE ? THEN 20
                WHEN author LIKE ? THEN 15
                ELSE 10
            END DESC',
            [$term, $term.'%', '%'.$escaped.'%', '%'.$escaped.'%', '%'.$escaped.'%', '%'.$escaped.'%'],
        )->orderByDesc('published_at');
    }

    private function highlightTerm(string $text, string $term): string
    {
        $text = $this->normalizeUtf8($text);
        $term = $this->normalizeUtf8($term);
        $position = mb_stripos($text, $term, 0, 'UTF-8');

        if ($position === false) {
            return $text;
        }

        $before = mb_substr($text, 0, $position);
        $match = mb_substr($text, $position, mb_strlen($term, 'UTF-8'), 'UTF-8');
        $after = mb_substr($text, $position + mb_strlen($term, 'UTF-8'), null, 'UTF-8');

        return $before.'<mark>'.$match.'</mark>'.$after;
    }

    /**
     * @return list<array{id: int, title: string, slug: string, published_at: string|null}>
     */
    private function articleSuggestions(string $term): array
    {
        $articles = Article::query()
            ->published()
            ->select('id', 'title', 'slug', 'published_at')
            ->latest('published_at')
            ->get()
            ->filter(fn (Article $article): bool => $this->containsTerm($article->title, $term))
            ->take(5);

        return $articles
            ->map(fn (Article $article): array => [
                'id' => $article->id,
                'title' => $this->normalizeUtf8((string) $article->title),
                'slug' => (string) $article->slug,
                'published_at' => $article->published_at?->toIso8601String(),
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array{id: int, name: string, slug: string, color: string}>
     */
    private function categorySuggestions(string $term, int $limit): array
    {
        return Category::query()
            ->active()
            ->get(['id', 'name', 'slug', 'color'])
            ->filter(fn (Category $category): bool => $this->containsTerm($category->name, $term))
            ->take($limit)
            ->map(fn (Category $category): array => [
                'id' => $category->id,
                'name' => $this->normalizeUtf8((string) $category->name),
                'slug' => (string) $category->slug,
                'color' => (string) $category->color,
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array{id: int, name: string, slug: string, color: string}>
     */
    private function tagSuggestions(string $term, int $limit): array
    {
        return Tag::query()
            ->orderByDesc('usage_count')
            ->get(['id', 'name', 'slug', 'color'])
            ->filter(fn (Tag $tag): bool => $this->containsTerm($tag->name, $term))
            ->take($limit)
            ->map(fn (Tag $tag): array => [
                'id' => $tag->id,
                'name' => $this->normalizeUtf8((string) $tag->name),
                'slug' => (string) $tag->slug,
                'color' => (string) $tag->color,
            ])
            ->values()
            ->all();
    }

    private function containsTerm(?string $value, string $term): bool
    {
        $value = $this->normalizeUtf8($value);
        $term = $this->normalizeUtf8($term);

        if ($value === '' || $term === '') {
            return false;
        }

        return mb_stripos($value, $term, 0, 'UTF-8') !== false;
    }

    private function normalizeUtf8(?string $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        $normalized = @iconv('UTF-8', 'UTF-8//IGNORE', $value);

        return $normalized === false ? $value : $normalized;
    }
}
