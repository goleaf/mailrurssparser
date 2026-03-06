<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ArticleIndexRequest;
use App\Http\Resources\ArticleCollection;
use App\Http\Resources\ArticleResource;
use App\Models\Article;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;

class ArticleController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(ArticleIndexRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $query = Article::published()
            ->with(['category', 'tags', 'subCategory'])
            ->when($validated['category'] ?? null, function (Builder $query, string $slug): void {
                $query->byCategory($slug);
            })
            ->when($validated['sub'] ?? null, function (Builder $query, string $slug): void {
                $query->bySubCategory($slug);
            })
            ->when($validated['tag'] ?? null, function (Builder $query, string $slug): void {
                $query->byTag($slug);
            })
            ->when($request->boolean('featured'), function (Builder $query): void {
                $query->featured();
            })
            ->when($request->boolean('breaking'), function (Builder $query): void {
                $query->breaking();
            })
            ->when(($validated['date_from'] ?? null) && ($validated['date_to'] ?? null), function (Builder $query) use ($validated): void {
                $query->byDateRange($validated['date_from'], $validated['date_to']);
            })
            ->when($validated['date'] ?? null, function (Builder $query, string $date): void {
                $query->byDate($date);
            })
            ->when($validated['search'] ?? null, function (Builder $query, string $term): void {
                if (trait_exists(\Laravel\Scout\Searchable::class)) {
                    $ids = Article::search($term)->get()->modelKeys();
                    $query->whereIn('id', $ids);

                    return;
                }

                $query->search($term);
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

    /**
     * Display the specified resource.
     */
    public function show(string $slug): JsonResponse
    {
        $article = Article::published()
            ->with(['category', 'tags', 'subCategory'])
            ->where('slug', $slug)
            ->firstOrFail();

        $article->incrementViews(request()->ip(), session()->getId());

        return ArticleResource::make($article)->response();
    }

    /**
     * Display featured articles.
     */
    public function featured(): JsonResponse
    {
        $articles = Article::published()
            ->featured()
            ->with(['category', 'tags'])
            ->orderByDesc('published_at')
            ->limit(10)
            ->get();

        return (new ArticleCollection($articles))->response();
    }

    /**
     * Display breaking articles.
     */
    public function breaking(): JsonResponse
    {
        $articles = Article::published()
            ->breaking()
            ->with(['category'])
            ->orderByDesc('published_at')
            ->limit(5)
            ->get();

        return (new ArticleCollection($articles))->response();
    }
}
