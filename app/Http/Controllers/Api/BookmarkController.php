<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\BookmarkCheckRequest;
use App\Http\Resources\ArticleCollection;
use App\Models\Article;
use App\Models\Bookmark;
use Illuminate\Http\Request;

class BookmarkController extends Controller
{
    public function index(Request $request): ArticleCollection
    {
        $bookmarks = Bookmark::query()
            ->where('session_hash', $this->sessionHash($request))
            ->with(['article.category'])
            ->latest('created_at')
            ->get();

        return new ArticleCollection($bookmarks->pluck('article')->filter()->values());
    }

    public function toggle(Request $request, int $articleId): \Illuminate\Http\JsonResponse
    {
        $article = Article::query()->findOrFail($articleId);
        $sessionHash = $this->sessionHash($request);

        $existing = Bookmark::query()
            ->where('session_hash', $sessionHash)
            ->where('article_id', $articleId)
            ->first();

        if ($existing !== null) {
            $existing->delete();
            $article->decrement('bookmarks_count');

            return response()->json(['bookmarked' => false, 'total' => max(0, $article->fresh()->bookmarks_count)]);
        }

        Bookmark::query()->create([
            'session_hash' => $sessionHash,
            'article_id' => $articleId,
        ]);

        $article->increment('bookmarks_count');

        return response()->json(['bookmarked' => true, 'total' => $article->fresh()->bookmarks_count]);
    }

    public function check(BookmarkCheckRequest $request): \Illuminate\Http\JsonResponse
    {
        $ids = $request->validated('ids');

        $bookmarkedIds = Bookmark::query()
            ->where('session_hash', $this->sessionHash($request))
            ->whereIn('article_id', $ids)
            ->pluck('article_id')
            ->all();

        return response()->json(['bookmarked_ids' => $bookmarkedIds]);
    }

    private function sessionHash(Request $request): string
    {
        return hash('sha256', (string) $request->ip().($request->userAgent() ?? ''));
    }
}
