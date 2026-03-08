<?php

use App\Models\Article;
use App\Models\Category;
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
