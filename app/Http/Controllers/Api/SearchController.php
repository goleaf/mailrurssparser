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
use App\Services\ArticleSearchService;
use Illuminate\Support\Str;

class SearchController extends Controller
{
    public function __construct(
        private readonly ArticleSearchService $articleSearch,
    ) {}

    public function index(ArticleSearchRequest $request): ArticleCollection
    {
        $validated = $request->validated();
        $term = $this->normalizeUtf8((string) $validated['q']);
        $perPage = (int) ($validated['per_page'] ?? 20);
        $articles = $this->articleSearch->search(
            $term,
            $validated,
            $validated['sort'] ?? 'relevance',
            $perPage,
        );

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
        $articles = $this->articleSearch->suggestArticles($term, 12);

        return $articles
            ->filter(fn (Article $article): bool => $this->containsTerm($article->title, $term))
            ->sortBy(fn (Article $article): array => [
                $this->autocompleteRank($article->title, $term),
                -($article->published_at?->getTimestamp() ?? 0),
            ])
            ->take(5)
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
            ->sortBy(fn (Category $category): array => [
                $this->autocompleteRank($category->name, $term),
                (int) $category->order,
                $this->normalizeUtf8((string) $category->name),
            ])
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
            ->sortBy(fn (Tag $tag): array => [
                $this->autocompleteRank($tag->name, $term),
                -((int) $tag->usage_count),
                $this->normalizeUtf8((string) $tag->name),
            ])
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

    private function autocompleteRank(?string $value, string $term): int
    {
        $value = $this->normalizeUtf8($value);
        $term = $this->normalizeUtf8($term);

        if ($value === '' || $term === '') {
            return 3;
        }

        $normalizedValue = mb_strtolower($value, 'UTF-8');
        $normalizedTerm = mb_strtolower($term, 'UTF-8');

        if ($normalizedValue === $normalizedTerm) {
            return 0;
        }

        if (str_starts_with($normalizedValue, $normalizedTerm)) {
            return 1;
        }

        return 2;
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
