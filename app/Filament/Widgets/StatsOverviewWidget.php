<?php

namespace App\Filament\Widgets;

use App\Models\Article;
use App\Models\ArticleView;
use App\Models\RssFeed;
use App\Models\Tag;
use App\Services\MetricReportService;
use App\Services\TrackedMetric;
use Filament\Support\Colors\Color;
use Filament\Widgets\StatsOverviewWidget as BaseStatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StatsOverviewWidget extends BaseStatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $metricTotals = app(MetricReportService::class)->totals([
            TrackedMetric::BookmarkAdded,
            TrackedMetric::RssArticleImported,
        ], 24);
        $newsletterTotals = app(MetricReportService::class)->totals([
            TrackedMetric::NewsletterSubscription,
        ], 168);
        $startDate = today()->minus(days: 6);
        $articleCounts = Article::query()
            ->published()
            ->whereDate('published_at', '>=', $startDate)
            ->selectRaw("strftime('%Y-%m-%d', published_at) as date, COUNT(*) as aggregate")
            ->groupBy('date')
            ->orderBy('date')
            ->pluck('aggregate', 'date');

        $chart = collect(range(6, 0))
            ->map(fn (int $days): int => (int) ($articleCounts[today()->minus(days: $days)->toDateString()] ?? 0))
            ->all();

        return [
            Stat::make('Total Published', Article::query()->published()->count())
                ->chart($chart)
                ->color('success'),
            Stat::make('Today', Article::query()->published()->whereDate('published_at', today())->count())
                ->color('info'),
            Stat::make('Views Today', ArticleView::query()->whereDate('viewed_at', today())->count())
                ->color('warning'),
            Stat::make('Total Views', number_format((int) Article::query()->published()->sum('views_count')))
                ->color('gray'),
            Stat::make('Active Feeds', RssFeed::query()->active()->count())
                ->color('primary'),
            Stat::make('Trending Tags', Tag::query()->where('is_trending', true)->count())
                ->color(Color::Purple),
            Stat::make('Bookmarks 24h', $metricTotals[TrackedMetric::BookmarkAdded->value] ?? 0)
                ->color('info'),
            Stat::make('Subscriptions 7d', $newsletterTotals[TrackedMetric::NewsletterSubscription->value] ?? 0)
                ->color('success'),
            Stat::make('RSS Imports 24h', $metricTotals[TrackedMetric::RssArticleImported->value] ?? 0)
                ->color('warning'),
        ];
    }
}
