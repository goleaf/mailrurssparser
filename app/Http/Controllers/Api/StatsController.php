<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StatsCalendarRequest;
use App\Http\Resources\ArticleCollection;
use App\Models\Article;
use App\Models\ArticleView;
use App\Models\Category;
use App\Models\RssFeed;
use App\Models\Tag;
use Illuminate\Http\JsonResponse;

class StatsController extends Controller
{
    public function overview(): JsonResponse
    {
        $today = today();
        $sevenDaysAgo = $today->copy()->subDays(6)->startOfDay();

        $viewsLast7Days = ArticleView::query()
            ->selectRaw('strftime("%Y-%m-%d", viewed_at) as date, COUNT(*) as count')
            ->where('viewed_at', '>=', $sevenDaysAgo)
            ->groupBy('date')
            ->orderBy('date')
            ->pluck('count', 'date')
            ->toArray();

        $articlesLast7Days = Article::published()
            ->selectRaw('strftime("%Y-%m-%d", published_at) as date, COUNT(*) as count')
            ->where('published_at', '>=', $sevenDaysAgo)
            ->groupBy('date')
            ->orderBy('date')
            ->pluck('count', 'date')
            ->toArray();

        return response()->json([
            'total_articles' => Article::published()->count(),
            'today_articles' => Article::published()->whereDate('published_at', $today)->count(),
            'total_views' => Article::published()->sum('views_count'),
            'today_views' => ArticleView::whereDate('viewed_at', $today)->count(),
            'total_categories' => Category::active()->count(),
            'total_tags' => Tag::count(),
            'total_feeds' => RssFeed::active()->count(),
            'views_last_7_days' => $viewsLast7Days,
            'articles_last_7_days' => $articlesLast7Days,
        ]);
    }

    public function popular(): JsonResponse
    {
        $articles = Article::published()
            ->with(['category', 'tags'])
            ->orderByDesc('views_count')
            ->limit(10)
            ->get();

        return (new ArticleCollection($articles))->response();
    }

    public function calendar(StatsCalendarRequest $request, int $year, int $month): JsonResponse
    {
        $validated = $request->validated();

        $year = (int) $validated['year'];
        $month = (int) $validated['month'];

        $calendar = Article::published()
            ->selectRaw('CAST(strftime("%d", published_at) AS INTEGER) as day, COUNT(*) as count')
            ->whereYear('published_at', $year)
            ->whereMonth('published_at', $month)
            ->groupBy('day')
            ->orderBy('day')
            ->pluck('count', 'day');

        return response()->json($calendar);
    }

    public function feedsStatus(): JsonResponse
    {
        $feeds = RssFeed::with('category')->get();

        return response()->json([
            'data' => $feeds->map(function (RssFeed $feed): array {
                return [
                    'id' => $feed->id,
                    'title' => $feed->title,
                    'url' => $feed->url,
                    'category_name' => $feed->category?->name,
                    'is_active' => $feed->is_active,
                    'last_parsed_at' => $feed->last_parsed_at,
                    'articles_parsed_total' => $feed->articles_parsed_total,
                    'last_run_new_count' => $feed->last_run_new_count,
                    'last_error' => $feed->last_error,
                ];
            })->all(),
        ]);
    }
}
