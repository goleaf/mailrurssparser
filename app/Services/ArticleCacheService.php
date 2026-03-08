<?php

namespace App\Services;

use App\Http\Resources\TagResource;
use App\Models\Article;
use App\Models\ArticleView;
use App\Models\Category;
use App\Models\RssFeed;
use App\Models\Tag;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\Cache;

use function Illuminate\Support\hours;
use function Illuminate\Support\minutes;

class ArticleCacheService
{
    public function getCategories(): EloquentCollection
    {
        return $this->memoizedCache()->flexible(ArticleCacheKey::Categories, [minutes(15), hours(1)], function () {
            return Category::query()
                ->active()
                ->withCount(['articles' => fn ($query) => $query->published()])
                ->get();
        });
    }

    public function getTrendingTags(int $limit = 30): EloquentCollection
    {
        return $this->memoizedCache()->flexible(ArticleCacheKey::trendingTags($limit), [minutes(10), minutes(30)], function () use ($limit) {
            return Tag::query()
                ->orderByDesc('usage_count')
                ->limit($limit)
                ->get();
        });
    }

    public function getBreakingNews(): EloquentCollection
    {
        return $this->memoizedCache()->flexible(ArticleCacheKey::BreakingNews, [minutes(1), minutes(5)], function () {
            return Article::query()
                ->published()
                ->breaking()
                ->with(['category', 'tags'])
                ->orderByDesc('published_at')
                ->limit(10)
                ->get();
        });
    }

    public function getFeaturedArticles(): EloquentCollection
    {
        return $this->memoizedCache()->flexible(ArticleCacheKey::FeaturedArticles, [minutes(5), minutes(15)], function () {
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
        return $this->memoizedCache()->flexible(ArticleCacheKey::StatsOverview, [minutes(2), minutes(10)], function (): array {
            $lastParse = RssFeed::query()
                ->active()
                ->orderByDesc('last_parsed_at')
                ->value('last_parsed_at');

            return [
                'articles' => [
                    'total' => Article::query()->published()->count(),
                    'today' => Article::query()->published()->whereDate('published_at', today())->count(),
                    'this_week' => Article::query()->published()->where('published_at', '>=', now()->minus(days: 7))->count(),
                    'breaking' => Article::query()->published()->breaking()->count(),
                    'featured' => Article::query()->published()->featured()->count(),
                ],
                'views' => [
                    'total' => Article::query()->published()->sum('views_count'),
                    'today' => ArticleView::query()->whereDate('viewed_at', today())->count(),
                    'this_week' => ArticleView::query()->where('viewed_at', '>=', now()->minus(days: 7))->count(),
                    'unique_today' => ArticleView::query()->whereDate('viewed_at', today())->distinct('ip_hash')->count('ip_hash'),
                ],
                'top_categories' => Category::query()
                    ->active()
                    ->withCount(['articles as article_count' => fn (Builder $query) => $query->published()])
                    ->orderByDesc('article_count')
                    ->limit(8)
                    ->get()
                    ->map(fn (Category $category): array => [
                        'id' => $category->id,
                        'name' => $category->name,
                        'slug' => $category->slug,
                        'color' => $category->color,
                        'icon' => $category->icon,
                        'article_count' => $category->article_count,
                    ])
                    ->all(),
                'trending_tags' => TagResource::collection(
                    Tag::query()->orderByDesc('usage_count')->limit(20)->get(),
                )->resolve(),
                'last_parse' => $lastParse?->toIso8601String(),
                'feeds' => [
                    'total' => RssFeed::query()->count(),
                    'active' => RssFeed::query()->active()->count(),
                    'errors' => RssFeed::query()->whereNotNull('last_error')->where('last_error', '!=', '')->count(),
                ],
            ];
        });
    }

    private function memoizedCache(): Repository
    {
        return Cache::memo();
    }
}
