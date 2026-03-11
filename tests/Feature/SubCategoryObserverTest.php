<?php

use App\Models\Category;
use App\Models\SubCategory;
use App\Services\ArticleCacheKey;
use Illuminate\Support\Facades\Cache;

it('forgets category navigation caches when subcategories change', function () {
    $category = Category::factory()->create();

    Cache::put(ArticleCacheKey::Categories, ['stale'], 600);
    Cache::put(ArticleCacheKey::flexibleCreated(ArticleCacheKey::Categories), now()->getTimestamp(), 600);

    $subCategory = SubCategory::factory()->forCategory($category)->create();

    expect(Cache::missing(ArticleCacheKey::Categories))->toBeTrue()
        ->and(Cache::missing(ArticleCacheKey::flexibleCreated(ArticleCacheKey::Categories)))->toBeTrue();

    Cache::put(ArticleCacheKey::Categories, ['stale'], 600);
    Cache::put(ArticleCacheKey::flexibleCreated(ArticleCacheKey::Categories), now()->getTimestamp(), 600);

    $subCategory->update([
        'name' => 'Обновлённая подрубрика',
    ]);

    expect(Cache::missing(ArticleCacheKey::Categories))->toBeTrue()
        ->and(Cache::missing(ArticleCacheKey::flexibleCreated(ArticleCacheKey::Categories)))->toBeTrue();

    Cache::put(ArticleCacheKey::Categories, ['stale'], 600);
    Cache::put(ArticleCacheKey::flexibleCreated(ArticleCacheKey::Categories), now()->getTimestamp(), 600);

    $subCategory->delete();

    expect(Cache::missing(ArticleCacheKey::Categories))->toBeTrue()
        ->and(Cache::missing(ArticleCacheKey::flexibleCreated(ArticleCacheKey::Categories)))->toBeTrue();
});
