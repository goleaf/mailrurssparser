<?php

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
