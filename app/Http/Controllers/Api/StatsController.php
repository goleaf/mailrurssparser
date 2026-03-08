<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StatsCalendarRequest;
use App\Http\Requests\Api\StatsChartRequest;
use App\Http\Requests\Api\StatsPopularRequest;
use App\Models\Article;
use App\Models\ArticleView;
use App\Models\Category;
use App\Models\RssFeed;
use App\Models\RssParseLog;
use App\Services\ArticleCacheService;
use App\Services\MetricReportService;
use App\Services\TrackedMetric;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class StatsController extends Controller
{
    public function overview(ArticleCacheService $cacheService): JsonResponse
    {
        return response()
            ->json($cacheService->getStatsOverview())
            ->header('Cache-Control', 'public, max-age=120, stale-while-revalidate=480');
    }

    public function metrics(MetricReportService $metrics): JsonResponse
    {
        $timeline = $metrics->timeline([
            TrackedMetric::ArticleView,
            TrackedMetric::BookmarkAdded,
            TrackedMetric::NewsletterSubscription,
            TrackedMetric::RssArticleImported,
        ], 24);
        $summary24Hours = $metrics->totals([
            TrackedMetric::ArticleView,
            TrackedMetric::ArticleUniqueView,
            TrackedMetric::BookmarkAdded,
            TrackedMetric::RssArticleImported,
            TrackedMetric::RssParseFailure,
        ], 24);
        $summary7Days = $metrics->totals([
            TrackedMetric::NewsletterSubscription,
            TrackedMetric::NewsletterConfirmation,
            TrackedMetric::NewsletterUnsubscription,
        ], 168);

        return response()
            ->json([
                'summary' => [
                    'article_views_24h' => $summary24Hours[TrackedMetric::ArticleView->value] ?? 0,
                    'unique_article_views_24h' => $summary24Hours[TrackedMetric::ArticleUniqueView->value] ?? 0,
                    'bookmarks_added_24h' => $summary24Hours[TrackedMetric::BookmarkAdded->value] ?? 0,
                    'rss_imports_24h' => $summary24Hours[TrackedMetric::RssArticleImported->value] ?? 0,
                    'rss_failures_24h' => $summary24Hours[TrackedMetric::RssParseFailure->value] ?? 0,
                    'newsletter_subscriptions_7d' => $summary7Days[TrackedMetric::NewsletterSubscription->value] ?? 0,
                    'newsletter_confirmations_7d' => $summary7Days[TrackedMetric::NewsletterConfirmation->value] ?? 0,
                    'newsletter_unsubscriptions_7d' => $summary7Days[TrackedMetric::NewsletterUnsubscription->value] ?? 0,
                ],
                'timeline' => $timeline,
                'top_articles' => [
                    'views' => collect($metrics->topMeasurables(TrackedMetric::ArticleView, Article::class))
                        ->map(fn (array $entry): array => [
                            'id' => $entry['model']->id,
                            'title' => $entry['model']->title,
                            'slug' => $entry['model']->slug,
                            'views_7d' => $entry['total'],
                        ])
                        ->all(),
                    'bookmarks' => collect($metrics->topMeasurables(TrackedMetric::BookmarkAdded, Article::class))
                        ->map(fn (array $entry): array => [
                            'id' => $entry['model']->id,
                            'title' => $entry['model']->title,
                            'slug' => $entry['model']->slug,
                            'bookmarks_7d' => $entry['total'],
                        ])
                        ->all(),
                ],
                'top_feeds' => [
                    'imports' => collect($metrics->topMeasurables(TrackedMetric::RssArticleImported, RssFeed::class))
                        ->map(fn (array $entry): array => [
                            'id' => $entry['model']->id,
                            'title' => $entry['model']->title,
                            'imports_7d' => $entry['total'],
                        ])
                        ->all(),
                    'failures' => collect($metrics->topMeasurables(TrackedMetric::RssParseFailure, RssFeed::class))
                        ->map(fn (array $entry): array => [
                            'id' => $entry['model']->id,
                            'title' => $entry['model']->title,
                            'failures_7d' => $entry['total'],
                        ])
                        ->all(),
                ],
            ])
            ->header('Cache-Control', 'public, max-age=60');
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
            '7d' => ['%Y-%m-%d %H:00', now()->minus(days: 7)],
            '90d' => ['%Y-%W', now()->minus(days: 90)],
            default => ['%Y-%m-%d', now()->minus(days: 30)],
        };

        $rows = DB::table($column['table'])
            ->selectRaw("strftime('{$format}', {$column['column']}) as label, {$column['aggregate']} as count")
            ->where($column['column'], '>=', $start)
            ->groupBy('label')
            ->orderBy('label')
            ->get();

        $response = [
            'labels' => $rows->pluck('label')->all(),
            'data' => $rows->pluck('count')->map(fn ($count) => (int) $count)->all(),
            'period' => $period,
        ];

        if ($type === 'articles') {
            $seriesRows = Article::query()
                ->selectRaw("strftime('{$format}', articles.published_at) as label, categories.id as category_id, categories.name as category_name, categories.color as category_color, COUNT(*) as count")
                ->join('categories', 'categories.id', '=', 'articles.category_id')
                ->published()
                ->where('articles.published_at', '>=', $start)
                ->groupByRaw('label, categories.id, categories.name, categories.color')
                ->orderBy('label')
                ->get();

            $topCategoryIds = $seriesRows
                ->groupBy('category_id')
                ->map(fn ($group) => $group->sum('count'))
                ->sortDesc()
                ->keys()
                ->take(5)
                ->all();

            $seriesGroups = $seriesRows
                ->whereIn('category_id', $topCategoryIds)
                ->groupBy('category_id');

            $response['series'] = $seriesGroups->map(function ($group, $categoryId) use ($response): array {
                $firstRow = $group->first();
                $countsByLabel = $group->pluck('count', 'label');

                return [
                    'id' => (int) $categoryId,
                    'name' => $firstRow->category_name,
                    'color' => $firstRow->category_color ?: '#3B82F6',
                    'data' => collect($response['labels'])
                        ->map(fn ($label): int => (int) ($countsByLabel[$label] ?? 0))
                        ->all(),
                ];
            })->values()->all();
        }

        return response()->json($response);
    }

    public function popular(StatsPopularRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $period = $validated['period'] ?? 'week';
        $limit = (int) ($validated['limit'] ?? 10);

        [$start, $end, $previousStart, $previousEnd] = match ($period) {
            'today' => [today(), now(), today()->minus(days: 1), today()],
            'month' => [now()->minus(months: 1), now(), now()->minus(months: 2), now()->minus(months: 1)],
            'all' => [null, null, null, null],
            default => [now()->minus(weeks: 1), now(), now()->minus(weeks: 2), now()->minus(weeks: 1)],
        };

        $baseQuery = ArticleView::query()
            ->join('articles', 'articles.id', '=', 'article_views.article_id')
            ->selectRaw('article_views.article_id, COUNT(*) as view_count')
            ->where('articles.status', 'published')
            ->whereNotNull('articles.published_at')
            ->where('articles.published_at', '<=', now())
            ->when($start !== null && $end !== null, function (Builder|QueryBuilder $query) use ($start, $end): void {
                $query->whereBetween('article_views.viewed_at', [$start, $end]);
            })
            ->groupBy('article_id')
            ->orderByDesc('view_count')
            ->limit($limit);

        $articleIds = (clone $baseQuery)->pluck('article_id');

        $articles = Article::query()
            ->with(['category', 'tags'])
            ->whereIn('id', $articleIds)
            ->get()
            ->keyBy('id');

        $data = $baseQuery->get()->map(function ($row) use ($articles, $period, $previousStart, $previousEnd): array {
            $article = $articles->get($row->article_id);

            $previousCount = 0;

            if ($period !== 'all' && $previousStart !== null && $previousEnd !== null) {
                $previousCount = ArticleView::query()
                    ->where('article_id', $row->article_id)
                    ->whereBetween('viewed_at', [$previousStart, $previousEnd])
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
                'shares_count' => (int) ($article?->shares_count ?? 0),
                'bookmarks_count' => (int) ($article?->bookmarks_count ?? 0),
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
        $latestStartedAt = RssParseLog::query()
            ->selectRaw('rss_feed_id, MAX(started_at) as latest_started_at')
            ->groupBy('rss_feed_id');

        $lastLogs = RssParseLog::query()
            ->joinSub($latestStartedAt, 'latest_logs', function ($join): void {
                $join->on('rss_parse_logs.rss_feed_id', '=', 'latest_logs.rss_feed_id')
                    ->on('rss_parse_logs.started_at', '=', 'latest_logs.latest_started_at');
            })
            ->get()
            ->keyBy('rss_feed_id');

        $averageDurations = RssParseLog::query()
            ->selectRaw('rss_feed_id, AVG(duration_ms) as avg_duration_ms')
            ->groupBy('rss_feed_id')
            ->get()
            ->keyBy('rss_feed_id');

        $feeds = RssFeed::query()
            ->with('category')
            ->withCount([
                'articles',
                'articles as today_articles_count' => fn (Builder $query) => $query->whereDate('published_at', today()),
            ])
            ->get()
            ->map(function (RssFeed $feed) use ($averageDurations, $lastLogs): array {
                /** @var RssParseLog|null $lastLog */
                $lastLog = $lastLogs->get($feed->id);
                $avgDuration = (int) round((float) ($averageDurations->get($feed->id)->avg_duration_ms ?? 0));

                return [
                    'id' => $feed->id,
                    'title' => $feed->title,
                    'url' => $feed->url,
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
            ->where('is_active', true)
            ->withCount([
                'articles',
                'articles as published_count' => fn (Builder $query) => $query->published(),
            ])
            ->with([
                'articles' => fn ($query) => $query->published()->orderByDesc('views_count')->limit(1),
            ])
            ->orderByDesc('published_count')
            ->get()
            ->map(function (Category $category) use ($total): array {
                $topArticle = $category->articles->first();

                return [
                    'id' => $category->id,
                    'name' => $category->name,
                    'slug' => $category->slug,
                    'color' => $category->color,
                    'published_count' => $category->published_count,
                    'article_count' => $category->published_count,
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
