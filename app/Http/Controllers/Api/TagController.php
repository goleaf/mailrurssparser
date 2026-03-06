<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ArticleIndexRequest;
use App\Http\Resources\ArticleCollection;
use App\Models\Article;
use App\Models\Tag;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;

class TagController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): JsonResponse
    {
        $tags = Tag::orderByDesc('usage_count')
            ->limit(100)
            ->get();

        return response()->json([
            'data' => $tags->map(function (Tag $tag): array {
                return [
                    'id' => $tag->id,
                    'name' => $tag->name,
                    'slug' => $tag->slug,
                    'color' => $tag->color,
                    'usage_count' => $tag->usage_count,
                ];
            })->all(),
        ]);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $slug): JsonResponse
    {
        $tag = Tag::where('slug', $slug)
            ->withCount('articles')
            ->firstOrFail();

        return response()->json([
            'data' => [
                'id' => $tag->id,
                'name' => $tag->name,
                'slug' => $tag->slug,
                'color' => $tag->color,
                'usage_count' => $tag->usage_count,
                'article_count' => $tag->articles_count,
            ],
        ]);
    }

    /**
     * Display articles for the tag.
     */
    public function articles(ArticleIndexRequest $request, string $slug): JsonResponse
    {
        Tag::where('slug', $slug)->firstOrFail();

        $validated = $request->validated();

        $query = Article::published()
            ->byTag($slug)
            ->with(['category', 'tags'])
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
