<?php

use App\Models\Article;
use App\Models\ArticleView;
use App\Models\Category;
use App\Models\RssFeed;
use App\Models\Tag;
use App\Services\ArticleCacheKey;
use App\Services\ArticleCacheService;
use Illuminate\Support\Defer\DeferredCallbackCollection;
use Illuminate\Support\Facades\Cache;

test('article cache service stores categories using enum-backed cache keys', function () {
    Cache::forget(ArticleCacheKey::Categories);

    $category = Category::factory()->create([
        'slug' => 'politics',
    ]);

    Article::factory()->create([
        'category_id' => $category->id,
        'status' => 'published',
        'published_at' => now()->subHour(),
    ]);

    $categories = app(ArticleCacheService::class)->getCategories();

    expect($categories)->toHaveCount(1)
        ->and($categories->hasSole(fn (Category $item): bool => $item->id === $category->id))->toBeTrue()
        ->and(Cache::has(ArticleCacheKey::Categories))->toBeTrue()
        ->and(Cache::has(ArticleCacheKey::flexibleCreated(ArticleCacheKey::Categories)))->toBeTrue()
        ->and(Cache::get(ArticleCacheKey::Categories))->toHaveCount(1);
});

test('article cache service stores trending tags using centralized cache key generation', function () {
    Cache::forget(ArticleCacheKey::trendingTags(10));

    Tag::factory()->count(12)->create();

    $tags = app(ArticleCacheService::class)->getTrendingTags(10);

    expect($tags)->toHaveCount(10)
        ->and(Cache::has(ArticleCacheKey::trendingTags(10)))->toBeTrue()
        ->and(Cache::has(ArticleCacheKey::flexibleCreated(ArticleCacheKey::trendingTags(10))))->toBeTrue()
        ->and(Cache::get(ArticleCacheKey::trendingTags(10)))->toHaveCount(10);
});

test('article cache service stores overview analytics using the shared cache key', function () {
    Cache::forget(ArticleCacheKey::StatsOverview);

    $category = Category::factory()->create(['slug' => 'politics']);
    $feed = RssFeed::factory()->create([
        'category_id' => $category->id,
        'is_active' => true,
        'last_parsed_at' => now()->subMinute(),
    ]);
    $tag = Tag::factory()->create(['name' => 'Urgent', 'usage_count' => 12]);
    $article = Article::factory()->create([
        'category_id' => $category->id,
        'rss_feed_id' => $feed->id,
        'status' => 'published',
        'published_at' => now()->subHour(),
        'is_breaking' => true,
        'is_featured' => true,
        'views_count' => 15,
    ]);
    $article->tags()->attach($tag);

    ArticleView::factory()->create([
        'article_id' => $article->id,
        'ip_hash' => 'hash-1',
        'country_code' => 'DE',
        'timezone' => 'Europe/Berlin',
        'viewed_at' => today(),
    ]);
    ArticleView::factory()->create([
        'article_id' => $article->id,
        'ip_hash' => 'hash-2',
        'country_code' => 'DE',
        'timezone' => 'Europe/Berlin',
        'viewed_at' => today(),
    ]);
    ArticleView::factory()->create([
        'article_id' => $article->id,
        'ip_hash' => 'hash-3',
        'country_code' => 'FR',
        'timezone' => 'Europe/Paris',
        'viewed_at' => today(),
    ]);

    $overview = app(ArticleCacheService::class)->getStatsOverview();

    expect($overview['articles']['total'])->toBe(1)
        ->and($overview['articles']['breaking'])->toBe(1)
        ->and($overview['views']['total'])->toBe(15)
        ->and($overview['feeds']['active'])->toBe(1)
        ->and($overview['top_countries'])->toBe([
            ['country_code' => 'DE', 'view_count' => 2],
            ['country_code' => 'FR', 'view_count' => 1],
        ])
        ->and($overview['top_timezones'])->toBe([
            ['timezone' => 'Europe/Berlin', 'view_count' => 2],
            ['timezone' => 'Europe/Paris', 'view_count' => 1],
        ])
        ->and($overview['top_categories'])->toHaveCount(1)
        ->and($overview['trending_tags'])->toHaveCount(1)
        ->and(Cache::has(ArticleCacheKey::StatsOverview))->toBeTrue()
        ->and(Cache::has(ArticleCacheKey::flexibleCreated(ArticleCacheKey::StatsOverview)))->toBeTrue();
});

test('article cache service memoizes repeated category cache reads within the same request', function () {
    Cache::forget(ArticleCacheKey::Categories);

    $category = Category::factory()->create([
        'slug' => 'memoized-politics',
    ]);

    Article::factory()->create([
        'category_id' => $category->id,
        'status' => 'published',
        'published_at' => now()->subHour(),
    ]);

    $service = app(ArticleCacheService::class);

    $service->getCategories();

    $memoizedStore = Cache::memo()->getStore();
    $memoizedEntries = (fn (): array => $this->cache)->call($memoizedStore);

    expect($memoizedEntries)->toBeEmpty();

    $service->getCategories();

    $memoizedEntries = (fn (): array => $this->cache)->call($memoizedStore);

    expect($memoizedEntries)->toHaveKey(Cache::memo()->getPrefix().ArticleCacheKey::Categories->value);
});

test('article cache service serves stale stats while refreshing the cache in the background', function () {
    $category = Category::factory()->create(['slug' => 'fresh-news']);
    $feed = RssFeed::factory()->create([
        'category_id' => $category->id,
        'is_active' => true,
        'last_parsed_at' => now()->subMinute(),
    ]);
    $tag = Tag::factory()->create(['name' => 'Fresh', 'usage_count' => 3]);
    $article = Article::factory()->create([
        'category_id' => $category->id,
        'rss_feed_id' => $feed->id,
        'status' => 'published',
        'published_at' => now()->subMinute(),
        'views_count' => 7,
    ]);
    $article->tags()->attach($tag);

    Cache::forget(ArticleCacheKey::StatsOverview);
    Cache::forget(ArticleCacheKey::flexibleCreated(ArticleCacheKey::StatsOverview));

    Cache::put(ArticleCacheKey::StatsOverview, [
        'articles' => [
            'total' => 0,
            'today' => 0,
            'this_week' => 0,
            'breaking' => 0,
            'featured' => 0,
        ],
        'views' => [
            'total' => 0,
            'today' => 0,
            'this_week' => 0,
            'unique_today' => 0,
        ],
        'top_countries' => [],
        'top_timezones' => [],
        'top_categories' => [],
        'trending_tags' => [],
        'last_parse' => null,
        'feeds' => [
            'total' => 0,
            'active' => 0,
            'errors' => 0,
        ],
    ], 600);
    Cache::put(
        ArticleCacheKey::flexibleCreated(ArticleCacheKey::StatsOverview),
        now()->minus(minutes: 5)->getTimestamp(),
        600,
    );

    $overview = app(ArticleCacheService::class)->getStatsOverview();

    $deferredCallbacks = app(DeferredCallbackCollection::class);

    expect($overview['articles']['total'])->toBe(0)
        ->and($deferredCallbacks)->toHaveCount(1);

    $deferredCallbacks->invoke();

    $refreshedOverview = Cache::get(ArticleCacheKey::StatsOverview);

    expect($refreshedOverview['articles']['total'])->toBe(1)
        ->and($refreshedOverview['views']['total'])->toBe(7)
        ->and($refreshedOverview['feeds']['active'])->toBe(1);
});
