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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

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
        $threatTable = config('threat-detection.table_name', 'threat_logs');
        $threatDashboardUrl = Route::has('threat-detection.dashboard')
            ? route('threat-detection.dashboard')
            : null;
        $todayStart = today()->startOfDay();
        $todayEnd = $todayStart->endOfDay();
        $startDate = $todayStart->minus(days: 6);
        $threatsLastDay = 0;
        $highThreatsLastDay = 0;

        if (config('threat-detection.enabled') && Schema::hasTable($threatTable)) {
            $threatsLastDay = DB::table($threatTable)
                ->where('created_at', '>=', now()->minus(days: 1))
                ->count();
            $highThreatsLastDay = DB::table($threatTable)
                ->where('created_at', '>=', now()->minus(days: 1))
                ->where('threat_level', 'high')
                ->count();
        }

        $topCountry = ArticleView::query()
            ->viewedSince(now()->minus(days: 7))
            ->withCountryCode()
            ->selectRaw('UPPER(country_code) as country_code, COUNT(*) as aggregate')
            ->groupByRaw('UPPER(country_code)')
            ->orderByDesc('aggregate')
            ->first();
        $topTimezone = ArticleView::query()
            ->viewedSince(now()->minus(days: 7))
            ->withTimezone()
            ->selectRaw('timezone, COUNT(*) as aggregate')
            ->groupBy('timezone')
            ->orderByDesc('aggregate')
            ->first();
        $articleCounts = Article::query()
            ->publishedSince($startDate)
            ->selectRaw("strftime('%Y-%m-%d', published_at) as date, COUNT(*) as aggregate")
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->pluck(
                fn (object $row): int => (int) $row->aggregate,
                fn (object $row): string => (string) $row->date,
            );

        $chart = collect(range(6, 0))
            ->map(fn (int $days): int => (int) ($articleCounts[today()->minus(days: $days)->toDateString()] ?? 0))
            ->all();

        $threatStat = Stat::make('Threats 24h', $threatsLastDay)
            ->description($highThreatsLastDay > 0 ? number_format($highThreatsLastDay).' high severity' : 'No high severity threats')
            ->color($highThreatsLastDay > 0 ? 'danger' : 'gray');

        if ($threatDashboardUrl !== null) {
            $threatStat->url($threatDashboardUrl);
        }

        return [
            Stat::make('Total Published', Article::query()->published()->count())
                ->chart($chart)
                ->color('success'),
            Stat::make('Today', Article::query()->publishedBetween($todayStart, $todayEnd)->count())
                ->color('info'),
            Stat::make('Views Today', ArticleView::query()->viewedBetween($todayStart, $todayEnd)->count())
                ->color('warning'),
            Stat::make('Total Views', number_format((int) Article::query()->published()->sum('views_count')))
                ->color('gray'),
            Stat::make('Top Country 7d', $topCountry?->country_code ?? '—')
                ->description($topCountry !== null ? number_format((int) $topCountry->aggregate).' views' : 'No geo data yet')
                ->color('primary'),
            Stat::make('Top TZ 7d', $topTimezone?->timezone ?? '—')
                ->description($topTimezone !== null ? number_format((int) $topTimezone->aggregate).' views' : 'No timezone data yet')
                ->color('gray'),
            $threatStat,
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
