<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ArticleIndexRequest;
use App\Http\Resources\ArticleCollection;
use App\Http\Resources\CategoryResource;
use App\Models\Article;
use App\Models\Category;
use App\Services\ArticleCacheService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;

class CategoryController extends Controller
{
    public function index(ArticleCacheService $cacheService): JsonResponse
    {
        $categories = $cacheService->getCategories()->load('subCategories');

        return CategoryResource::collection($categories)
            ->response()
            ->header('Cache-Control', 'public, max-age=3600');
    }

    public function show(string $slug): JsonResponse
    {
        $category = Category::query()
            ->where('slug', $slug)
            ->withCount(['articles' => fn (Builder $query) => $query->published()])
            ->with(['subCategories', 'rssFeeds'])
            ->firstOrFail();

        return response()->json([
            'data' => array_merge(
                (new CategoryResource($category))->resolve(),
                [
                    'rss_feeds' => $category->rssFeeds->map(fn ($feed): array => [
                        'id' => $feed->id,
                        'title' => $feed->title,
                        'url' => $feed->url,
                        'is_active' => $feed->is_active,
                        'last_parsed_at' => $feed->last_parsed_at?->toIso8601String(),
                        'next_parse_at' => $feed->next_parse_at?->toIso8601String(),
                    ])->all(),
                ],
            ),
        ]);
    }

    public function articles(ArticleIndexRequest $request, string $slug): ArticleCollection
    {
        Category::query()->where('slug', $slug)->firstOrFail();

        $validated = $request->validated();
        $query = Article::query()
            ->published()
            ->byCategory($slug)
            ->with(['category', 'tags', 'subCategory']);

        $query
            ->when($validated['tag'] ?? null, fn (Builder $query, string $tagSlug) => $query->byTag($tagSlug))
            ->when($validated['tags'] ?? null, fn (Builder $query, array $tags) => $query->byTag($tags))
            ->when(($validated['date_from'] ?? null) && ($validated['date_to'] ?? null), fn (Builder $query) => $query->byDateRange($validated['date_from'], $validated['date_to']))
            ->when($validated['date'] ?? null, fn (Builder $query, string $date) => $query->byDate($date));

        match ($validated['sort'] ?? 'latest') {
            'popular' => $query->popular(),
            'oldest' => $query->orderBy('published_at'),
            default => $query->orderByDesc('is_breaking')->orderByDesc('is_pinned')->orderByDesc('published_at'),
        };

        $articles = $query->paginate((int) ($validated['per_page'] ?? 20))->appends($request->except('page'));

        return new ArticleCollection($articles);
    }
}
