<?php

namespace App\Filament\Widgets;

use App\Models\Article;
use App\Models\ArticleView;
use App\Models\RssFeed;
use App\Models\Tag;
use Filament\Support\Colors\Color;
use Filament\Widgets\StatsOverviewWidget as BaseStatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StatsOverviewWidget extends BaseStatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $startDate = today()->subDays(6);
        $articleCounts = Article::query()
            ->published()
            ->whereDate('published_at', '>=', $startDate)
            ->selectRaw("strftime('%Y-%m-%d', published_at) as date, COUNT(*) as aggregate")
            ->groupBy('date')
            ->orderBy('date')
            ->pluck('aggregate', 'date');

        $chart = collect(range(6, 0))
            ->map(fn (int $days): int => (int) ($articleCounts[today()->subDays($days)->toDateString()] ?? 0))
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
        ];
    }
}
