<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StatsCalendarRequest;
use App\Http\Requests\Api\StatsChartRequest;
use App\Http\Requests\Api\StatsPopularRequest;
use App\Http\Resources\TagResource;
use App\Models\Article;
use App\Models\ArticleView;
use App\Models\Category;
use App\Models\RssFeed;
use App\Models\Tag;
use App\Services\ArticleCacheService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class StatsController extends Controller
{
    public function overview(ArticleCacheService $cacheService): JsonResponse
    {
        $cached = $cacheService->getStatsOverview();
        $lastParse = RssFeed::query()->active()->orderByDesc('last_parsed_at')->value('last_parsed_at');

        return response()->json([
            'articles' => [
                'total' => Article::query()->published()->count(),
                'today' => Article::query()->published()->whereDate('published_at', today())->count(),
                'this_week' => Article::query()->published()->where('published_at', '>=', now()->subDays(7))->count(),
                'breaking' => Article::query()->published()->breaking()->count(),
                'featured' => Article::query()->published()->featured()->count(),
            ],
            'views' => [
                'total' => Article::query()->published()->sum('views_count'),
                'today' => ArticleView::query()->whereDate('viewed_at', today())->count(),
                'this_week' => ArticleView::query()->where('viewed_at', '>=', now()->subDays(7))->count(),
                'unique_today' => ArticleView::query()->whereDate('viewed_at', today())->distinct('ip_hash')->count('ip_hash'),
            ],
            'top_categories' => Category::query()
                ->active()
                ->withCount(['articles' => fn (Builder $query) => $query->published()])
                ->orderByDesc('articles_count')
                ->limit(8)
                ->get(),
            'trending_tags' => TagResource::collection(Tag::query()->orderByDesc('usage_count')->limit(20)->get())->resolve(),
            'last_parse' => $lastParse,
            'feeds' => [
                'total' => RssFeed::query()->count(),
                'active' => RssFeed::query()->active()->count(),
                'errors' => RssFeed::query()->whereNotNull('last_error')->where('last_error', '!=', '')->count(),
            ],
            'cached' => $cached,
        ])->header('Cache-Control', 'public, max-age=300');
    }

    public function chart(StatsChartRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $type = $validated['type'];
        $period = $validated['period'] ?? '30d';
        $column = match ($type) {
            'views' => ['table' => 'article_views', 'column' => 'viewed_at', 'aggregate' => 'COUNT(*)'],
            'shares' => ['table' => 'articles', 'column' => 'published_at', 'aggregate' => 'SUM(shares_count)'],
            default => ['table' => 'articles', 'column' => 'published_at', 'aggregate' => 'COUNT(*)'],
        };

        [$format, $start] = match ($period) {
            '7d' => ['%Y-%m-%d %H:00', now()->subDays(7)],
            '90d' => ['%Y-%W', now()->subDays(90)],
            default => ['%Y-%m-%d', now()->subDays(30)],
        };

        $rows = DB::table($column['table'])
            ->selectRaw("strftime('{$format}', {$column['column']}) as label, {$column['aggregate']} as count")
            ->where($column['column'], '>=', $start)
            ->groupBy('label')
            ->orderBy('label')
            ->get();

        return response()->json([
            'labels' => $rows->pluck('label')->all(),
            'data' => $rows->pluck('count')->map(fn ($count) => (int) $count)->all(),
            'period' => $period,
        ]);
    }

    public function popular(StatsPopularRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $period = $validated['period'] ?? 'week';
        $limit = (int) ($validated['limit'] ?? 10);

        [$start, $previousStart] = match ($period) {
            'today' => [today(), today()->subDay()],
            'month' => [now()->subMonth(), now()->subMonths(2)],
            'all' => [null, null],
            default => [now()->subWeek(), now()->subWeeks(2)],
        };

        $baseQuery = ArticleView::query()
            ->selectRaw('article_id, COUNT(*) as view_count')
            ->when($start !== null, fn ($query) => $query->where('viewed_at', '>=', $start))
            ->groupBy('article_id')
            ->orderByDesc('view_count')
            ->limit($limit);

        $articleIds = (clone $baseQuery)->pluck('article_id');

        $articles = Article::query()
            ->with(['category', 'tags'])
            ->whereIn('id', $articleIds)
            ->get()
            ->keyBy('id');

        $data = $baseQuery->get()->map(function ($row) use ($articles, $period, $previousStart, $start): array {
            $article = $articles->get($row->article_id);

            $previousCount = 0;

            if ($period !== 'all' && $previousStart !== null && $start !== null) {
                $previousCount = ArticleView::query()
                    ->where('article_id', $row->article_id)
                    ->whereBetween('viewed_at', [$previousStart, $start])
                    ->count();
            }

            $change = $previousCount > 0
                ? round((((int) $row->view_count - $previousCount) / $previousCount) * 100, 1)
                : null;

            return [
                'article_id' => $row->article_id,
                'title' => $article?->title,
                'slug' => $article?->slug,
                'category' => $article?->category?->name,
                'view_count' => (int) $row->view_count,
                'change_percent' => $change,
            ];
        })->values();

        return response()->json(['data' => $data]);
    }

    public function calendar(StatsCalendarRequest $request, int $year, int $month): JsonResponse
    {
        $validated = $request->validated();

        $calendar = Article::query()
            ->published()
            ->selectRaw('CAST(strftime("%d", published_at) AS INTEGER) as day, COUNT(*) as count')
            ->whereYear('published_at', (int) $validated['year'])
            ->whereMonth('published_at', (int) $validated['month'])
            ->groupBy('day')
            ->orderBy('day')
            ->pluck('count', 'day');

        return response()->json($calendar);
    }

    public function feedsPerformance(): JsonResponse
    {
        $feeds = RssFeed::query()
            ->with('category')
            ->withCount([
                'articles',
                'articles as today_articles_count' => fn (Builder $query) => $query->whereDate('published_at', today()),
            ])
            ->get()
            ->map(function (RssFeed $feed): array {
                $lastLog = $feed->parseLogs()->latest('started_at')->first();
                $avgDuration = (int) $feed->parseLogs()->avg('duration_ms');

                return [
                    'id' => $feed->id,
                    'title' => $feed->title,
                    'category' => $feed->category?->name,
                    'total_articles' => $feed->articles_count,
                    'today_articles_count' => $feed->today_articles_count,
                    'last_run' => $lastLog ? [
                        'new_count' => $lastLog->new_count,
                        'skip_count' => $lastLog->skip_count,
                        'error_count' => $lastLog->error_count,
                        'duration_ms' => $lastLog->duration_ms,
                        'started_at' => $lastLog->started_at?->toIso8601String(),
                    ] : null,
                    'avg_duration_ms' => $avgDuration,
                ];
            });

        return response()->json(['data' => $feeds]);
    }

    public function categoryBreakdown(): JsonResponse
    {
        $total = max(1, Article::query()->published()->count());

        $categories = Category::query()
            ->active()
            ->withCount([
                'articles',
                'articles as published_count' => fn (Builder $query) => $query->published(),
            ])
            ->with([
                'articles' => fn (Builder $query) => $query->published()->orderByDesc('views_count')->limit(1),
            ])
            ->get()
            ->map(function (Category $category) use ($total): array {
                $topArticle = $category->articles->first();

                return [
                    'id' => $category->id,
                    'name' => $category->name,
                    'slug' => $category->slug,
                    'color' => $category->color,
                    'published_count' => $category->published_count,
                    'percentage' => round(($category->published_count / $total) * 100, 2),
                    'top_article' => $topArticle ? [
                        'id' => $topArticle->id,
                        'title' => $topArticle->title,
                        'slug' => $topArticle->slug,
                        'views_count' => $topArticle->views_count,
                    ] : null,
                ];
            });

        return response()->json(['data' => $categories]);
    }
}
