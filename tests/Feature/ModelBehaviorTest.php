<?php

use App\Models\Article;
use App\Models\Category;
use App\Models\RssFeed;
use App\Models\SubCategory;
use App\Models\Tag;

it('generates a category slug from the name when missing', function () {
    $category = Category::factory()->create([
        'name' => 'Breaking News',
        'slug' => '',
    ]);

    expect($category->slug)->toBe('breaking-news');
});

it('generates a sub category slug from the name when missing', function () {
    $subCategory = SubCategory::factory()->create([
        'name' => 'Local Updates',
        'slug' => '',
    ]);

    expect($subCategory->slug)->toBe('local-updates');
});

it('filters sub categories by category and finds them by slug', function () {
    $category = Category::factory()->create();
    $otherCategory = Category::factory()->create();

    $matchingSubCategory = SubCategory::factory()->forCategory($category)->create([
        'slug' => 'local-updates',
    ]);

    SubCategory::factory()->forCategory($otherCategory)->create([
        'slug' => 'other-updates',
    ]);

    expect(
        SubCategory::query()
            ->byCategory($category)
            ->pluck('id')
            ->all(),
    )->toBe([$matchingSubCategory->id])
        ->and(SubCategory::findBySlug('local-updates')?->id)->toBe($matchingSubCategory->id);
});

it('orders popular sub categories by related article count', function () {
    $category = Category::factory()->create();

    $lessPopular = SubCategory::factory()->forCategory($category)->create([
        'name' => 'City',
        'slug' => 'city',
    ]);
    $morePopular = SubCategory::factory()->forCategory($category)->create([
        'name' => 'Local',
        'slug' => 'local',
    ]);

    Article::factory()->published()->forSubCategory($lessPopular)->create();
    Article::factory()->count(2)->published()->forSubCategory($morePopular)->create();

    expect(SubCategory::query()->popular()->pluck('id')->take(2)->all())->toBe([
        $morePopular->id,
        $lessPopular->id,
    ]);
});

it('generates a tag slug from the name when missing', function () {
    $tag = Tag::factory()->create([
        'name' => 'Hot Takes',
        'slug' => '',
    ]);

    expect($tag->slug)->toBe('hot-takes');
});

it('returns active categories ordered by navigation order', function () {
    $second = Category::factory()->create([
        'is_active' => true,
        'order' => 2,
    ]);

    $first = Category::factory()->create([
        'is_active' => true,
        'order' => 1,
    ]);

    Category::factory()->create([
        'is_active' => false,
        'order' => 0,
    ]);

    expect(Category::active()->pluck('id')->all())->toBe([
        $first->id,
        $second->id,
    ]);
});

it('returns only active rss feeds', function () {
    $activeFeed = RssFeed::factory()->create(['is_active' => true]);
    RssFeed::factory()->create(['is_active' => false]);

    expect(RssFeed::active()->pluck('id')->all())->toBe([$activeFeed->id]);
});
