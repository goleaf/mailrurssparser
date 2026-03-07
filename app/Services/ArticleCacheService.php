<?php

namespace App\Services;

use App\Models\Article;
use App\Models\Category;
use App\Models\RssFeed;
use App\Models\Tag;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class ArticleCacheService
{
    public function getCategories()
    {
        return Cache::remember('categories', 3600, function () {
            return Category::query()
                ->active()
                ->withCount(['articles' => fn ($query) => $query->published()])
                ->get();
        });
    }

    public function getTrendingTags(int $limit = 30)
    {
        return Cache::remember("trending_tags_{$limit}", 1800, function () use ($limit) {
            return Tag::query()
                ->orderByDesc('usage_count')
                ->limit($limit)
                ->get();
        });
    }

    public function getBreakingNews()
    {
        return Cache::remember('breaking_news', 300, function () {
            return Article::query()
                ->published()
                ->breaking()
                ->with(['category', 'tags'])
                ->orderByDesc('published_at')
                ->limit(10)
                ->get();
        });
    }

    public function getFeaturedArticles()
    {
        return Cache::remember('featured_articles', 900, function () {
            return Article::query()
                ->published()
                ->featured()
                ->with(['category', 'tags'])
                ->orderByDesc('published_at')
                ->limit(10)
                ->get();
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function getStatsOverview(): array
    {
        return Cache::remember('stats_overview', 600, function (): array {
            return [
                'articles' => [
                    'total' => Article::query()->published()->count(),
                    'today' => Article::query()->published()->whereDate('published_at', today())->count(),
                ],
                'views' => [
                    'total' => Article::query()->published()->sum('views_count'),
                ],
                'feeds' => [
                    'active' => RssFeed::query()->active()->count(),
                ],
                'top_categories' => Category::query()
                    ->active()
                    ->withCount(['articles' => fn ($query) => $query->published()])
                    ->orderByDesc('articles_count')
                    ->limit(5)
                    ->get(),
                'trending_tags' => Tag::query()
                    ->orderByDesc('usage_count')
                    ->limit(10)
                    ->get(),
                'views_last_7_days' => DB::table('article_views')
                    ->selectRaw("strftime('%Y-%m-%d', viewed_at) as date, COUNT(*) as count")
                    ->where('viewed_at', '>=', now()->subDays(7))
                    ->groupBy('date')
                    ->orderBy('date')
                    ->pluck('count', 'date')
                    ->all(),
            ];
        });
    }
}
