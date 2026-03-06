<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ArticleCollection;
use App\Models\Article;
use App\Models\Tag;
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
                    'id_encoded' => $tag->id_encoded,
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
                'id_encoded' => $tag->id_encoded,
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
    public function articles(string $slug): JsonResponse
    {
        Tag::where('slug', $slug)->firstOrFail();

        $query = Article::published()
            ->byTag($slug)
            ->with(['category', 'tags'])
            ->orderByDesc('published_at');

        $articles = $query->paginate(20);

        return (new ArticleCollection($articles))->response();
    }
}
