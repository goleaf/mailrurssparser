<?php

use App\Models\Category;
use App\Models\RssFeed;
use Database\Seeders\CategorySeeder;

it('seeds categories and rss feeds from config', function () {
    $feeds = config('rss.feeds', []);

    $this->seed(CategorySeeder::class);

    expect(Category::count())->toBe(count($feeds))
        ->and(RssFeed::count())->toBe(count($feeds));

    $firstFeed = $feeds[0];

    $category = Category::query()
        ->where('slug', $firstFeed['category_slug'])
        ->first();

    expect($category)->not->toBeNull()
        ->and($category->name)->toBe($firstFeed['category_name'])
        ->and($category->rss_url)->toBe($firstFeed['url'])
        ->and($category->rss_key)->toBe($firstFeed['category_slug'])
        ->and($category->color)->toBe($firstFeed['category_color'])
        ->and($category->icon)->toBe($firstFeed['category_icon'])
        ->and($category->is_active)->toBeTrue()
        ->and($category->order)->toBe(0);

    $feed = RssFeed::query()
        ->where('url', $firstFeed['url'])
        ->first();

    expect($feed)->not->toBeNull()
        ->and($feed->title)->toBe($firstFeed['title'])
        ->and($feed->category_id)->toBe($category->id)
        ->and($feed->is_active)->toBeTrue();
});

it('does not duplicate records when seeding twice', function () {
    $feeds = config('rss.feeds', []);

    $this->seed(CategorySeeder::class);
    $this->seed(CategorySeeder::class);

    expect(Category::count())->toBe(count($feeds))
        ->and(RssFeed::count())->toBe(count($feeds));
});
