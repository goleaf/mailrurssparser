<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ArticleSearchRequest;
use App\Http\Resources\ArticleCollection;
use App\Models\Article;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;

class SearchController extends Controller
{
    public function index(ArticleSearchRequest $request): JsonResponse
    {
        $term = $request->validated('q');

        try {
            $articles = Article::search($term)
                ->query(function (Builder $query): void {
                    $query->published()->with(['category', 'tags']);
                })
                ->paginate(20);
        } catch (\Throwable) {
            $articles = Article::published()
                ->search($term)
                ->with(['category', 'tags'])
                ->paginate(20);
        }

        return (new ArticleCollection($articles))
            ->additional([
                'meta' => [
                    'query' => $term,
                    'total' => $articles->total(),
                ],
            ])
            ->response();
    }
}
