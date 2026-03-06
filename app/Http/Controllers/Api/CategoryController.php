<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ArticleIndexRequest;
use App\Http\Resources\ArticleCollection;
use App\Models\Article;
use App\Models\Category;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;

class CategoryController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): JsonResponse
    {
        $categories = Category::active()
            ->withCount('articles')
            ->with('subCategories')
            ->get();

        return response()->json([
            'data' => $categories->map(function (Category $category): array {
                return [
                    'id' => $category->id,
                    'name' => $category->name,
                    'slug' => $category->slug,
                    'color' => $category->color,
                    'icon' => $category->icon,
                    'description' => $category->description,
                    'article_count' => $category->articles_count,
                    'rss_url' => $category->rss_url,
                    'sub_categories' => $category->subCategories->map(function ($subCategory): array {
                        return [
                            'id' => $subCategory->id,
                            'name' => $subCategory->name,
                            'slug' => $subCategory->slug,
                            'description' => $subCategory->description,
                        ];
                    })->all(),
                ];
            })->all(),
        ]);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $slug): JsonResponse
    {
        $category = Category::where('slug', $slug)
            ->withCount('articles')
            ->with(['subCategories', 'rssFeeds'])
            ->firstOrFail();

        return response()->json([
            'data' => [
                'id' => $category->id,
                'name' => $category->name,
                'slug' => $category->slug,
                'color' => $category->color,
                'icon' => $category->icon,
                'description' => $category->description,
                'article_count' => $category->articles_count,
                'rss_url' => $category->rss_url,
                'sub_categories' => $category->subCategories->map(function ($subCategory): array {
                    return [
                        'id' => $subCategory->id,
                        'name' => $subCategory->name,
                        'slug' => $subCategory->slug,
                        'description' => $subCategory->description,
                    ];
                })->all(),
                'rss_feeds' => $category->rssFeeds->map(function ($feed): array {
                    return [
                        'id' => $feed->id,
                        'title' => $feed->title,
                        'url' => $feed->url,
                        'is_active' => $feed->is_active,
                        'last_parsed_at' => $feed->last_parsed_at,
                    ];
                })->all(),
            ],
        ]);
    }

    /**
     * Display articles for the category.
     */
    public function articles(ArticleIndexRequest $request, string $slug): JsonResponse
    {
        Category::where('slug', $slug)->firstOrFail();

        $validated = $request->validated();

        $query = Article::published()
            ->byCategory($slug)
            ->with(['category', 'tags', 'subCategory'])
            ->when($validated['tag'] ?? null, function (Builder $query, string $tagSlug): void {
                $query->byTag($tagSlug);
            })
            ->when(($validated['date_from'] ?? null) && ($validated['date_to'] ?? null), function (Builder $query) use ($validated): void {
                $query->byDateRange($validated['date_from'], $validated['date_to']);
            })
            ->when($validated['date'] ?? null, function (Builder $query, string $date): void {
                $query->byDate($date);
            });

        $sort = $validated['sort'] ?? 'latest';

        if ($sort === 'popular') {
            $query->orderByDesc('views_count');
        } elseif ($sort === 'oldest') {
            $query->orderBy('published_at');
        } else {
            $query->orderByDesc('published_at')->orderByDesc('is_breaking');
        }

        $perPage = $validated['per_page'] ?? 20;
        $articles = $query->paginate($perPage);

        return (new ArticleCollection($articles))->response();
    }
}
