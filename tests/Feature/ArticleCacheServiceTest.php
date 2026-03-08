<?php

use App\Models\Article;
use App\Models\ArticleView;
use App\Models\Category;
use App\Models\RssFeed;
use App\Models\Tag;
use App\Services\ArticleCacheKey;
use App\Services\ArticleCacheService;
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
        ->and(Cache::get(ArticleCacheKey::Categories))->toHaveCount(1);
});

test('article cache service stores trending tags using centralized cache key generation', function () {
    Cache::forget(ArticleCacheKey::trendingTags(10));

    Tag::factory()->count(12)->create();

    $tags = app(ArticleCacheService::class)->getTrendingTags(10);

    expect($tags)->toHaveCount(10)
        ->and(Cache::has(ArticleCacheKey::trendingTags(10)))->toBeTrue()
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
        'viewed_at' => today(),
    ]);

    $overview = app(ArticleCacheService::class)->getStatsOverview();

    expect($overview['articles']['total'])->toBe(1)
        ->and($overview['articles']['breaking'])->toBe(1)
        ->and($overview['views']['total'])->toBe(15)
        ->and($overview['feeds']['active'])->toBe(1)
        ->and($overview['top_categories'])->toHaveCount(1)
        ->and($overview['trending_tags'])->toHaveCount(1)
        ->and(Cache::has(ArticleCacheKey::StatsOverview))->toBeTrue();
});
