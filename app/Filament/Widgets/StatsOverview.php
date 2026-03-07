<?php

namespace App\Filament\Widgets;

use App\Models\Article;
use App\Models\ArticleView;
use App\Models\Category;
use App\Models\RssFeed;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StatsOverview extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        $startDate = today()->subDays(6);
        $articleCounts = Article::published()
            ->whereDate('published_at', '>=', $startDate)
            ->selectRaw('date(published_at) as date, count(*) as aggregate')
            ->groupBy('date')
            ->pluck('aggregate', 'date');

        $chart = collect(range(6, 0))
            ->map(fn (int $days): int => (int) ($articleCounts[today()->subDays($days)->toDateString()] ?? 0))
            ->all();

        return [
            Stat::make('Total Articles', Article::published()->count())
                ->color('success')
                ->icon('heroicon-o-newspaper')
                ->chart($chart),
            Stat::make('Today', Article::published()->whereDate('published_at', today())->count())
                ->color('info'),
            Stat::make('Views Today', ArticleView::whereDate('viewed_at', today())->count())
                ->color('warning'),
            Stat::make('Total Views', number_format((int) Article::published()->sum('views_count')))
                ->color('gray'),
            Stat::make('Categories', Category::active()->count())
                ->color('primary'),
            Stat::make('Active Feeds', RssFeed::active()->count())
                ->color('success'),
        ];
    }
}
