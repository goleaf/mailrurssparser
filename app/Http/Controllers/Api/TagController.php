<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ArticleIndexRequest;
use App\Http\Resources\ArticleCollection;
use App\Http\Resources\TagResource;
use App\Models\Article;
use App\Models\Tag;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TagController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $limit = max(1, min(100, (int) $request->integer('limit', 100)));
        $query = Tag::query()
            ->whereHas('articles', fn (Builder $query): Builder => $query->published())
            ->orderByDesc('usage_count');

        if ($request->boolean('trending')) {
            $query->trending();
        }

        return response()->json([
            'data' => TagResource::collection(
                $query->limit($limit)->get(),
            )->resolve(),
        ]);
    }

    public function trending(): JsonResponse
    {
        return response()->json([
            'data' => TagResource::collection(
                Tag::query()
                    ->trending()
                    ->whereHas('articles', fn (Builder $query): Builder => $query->published())
                    ->popular()
                    ->limit(30)
                    ->get(),
            )->resolve(),
        ]);
    }

    public function show(string $slug): JsonResponse
    {
        $tag = Tag::query()->where('slug', $slug)->withCount('articles')->firstOrFail();

        return response()->json([
            'data' => array_merge((new TagResource($tag))->resolve(), [
                'article_count' => $tag->articles_count,
            ]),
        ]);
    }

    public function articles(ArticleIndexRequest $request, string $slug): ArticleCollection
    {
        Tag::query()->where('slug', $slug)->firstOrFail();

        $validated = $request->validated();

        $query = Article::query()
            ->published()
            ->byTag($slug)
            ->with(['category', 'tags'])
            ->when(($validated['date_from'] ?? null) && ($validated['date_to'] ?? null), fn (Builder $query) => $query->byDateRange($validated['date_from'], $validated['date_to']))
            ->when($validated['date'] ?? null, fn (Builder $query, string $date) => $query->byDate($date))
            ->orderByDesc('published_at');

        $articles = $query->paginate((int) ($validated['per_page'] ?? 20))->appends($request->except('page'));

        return new ArticleCollection($articles);
    }
}
