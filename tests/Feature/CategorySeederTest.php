<?php

use App\Models\Article;
use App\Models\Category;
use App\Models\RssFeed;
use App\Models\SubCategory;
use Database\Seeders\CategorySeeder;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

it('seeds categories and rss feeds from config', function () {
    $feeds = Config::collection('rss.feeds', [])->all();

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
    $feeds = Config::collection('rss.feeds', [])->all();

    $this->seed(CategorySeeder::class);
    $this->seed(CategorySeeder::class);

    expect(Category::count())->toBe(count($feeds))
        ->and(RssFeed::count())->toBe(count($feeds));
});

it('creates configured sub categories and backfills feed article taxonomy and source names', function () {
    $economicsCategory = Category::factory()->create([
        'slug' => 'economics',
        'name' => 'Экономика',
    ]);

    $feed = RssFeed::factory()->create([
        'category_id' => $economicsCategory->id,
        'url' => rtrim((string) config('rss.feed_origin'), '/').'/rss/economics/',
        'title' => 'Экономика',
        'source_name' => 'legacy.example',
        'extra_settings' => [
            'status' => 'pending',
        ],
    ]);

    $article = Article::factory()->create([
        'category_id' => $economicsCategory->id,
        'rss_feed_id' => $feed->id,
        'source_name' => 'legacy.example',
        'author' => 'Old Mail Source',
        'sub_category_id' => null,
    ]);

    $this->seed(CategorySeeder::class);

    $feed->refresh();
    $article->refresh();
    $subCategory = SubCategory::query()
        ->where('category_id', $economicsCategory->id)
        ->where('slug', 'otrasli')
        ->first();

    expect($subCategory)->not->toBeNull()
        ->and($feed->title)->toBe('Экономика: Отрасли')
        ->and($feed->source_name)->toBe('')
        ->and($feed->extra_settings)->toMatchArray([
            'status' => 'pending',
            'sub_category_name' => 'Отрасли',
            'sub_category_slug' => 'otrasli',
        ])
        ->and($article->sub_category_id)->toBe($subCategory?->id)
        ->and($article->source_name)->toBeNull()
        ->and($article->author)->toBe('Редакция');
});

it('uses an empty string as the rss feed database default source name', function () {
    $category = Category::factory()->create();

    DB::table('rss_feeds')->insert([
        'category_id' => $category->id,
        'title' => 'Feed without explicit source',
        'url' => 'https://example.test/default-source-feed',
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    expect(
        DB::table('rss_feeds')
            ->where('url', 'https://example.test/default-source-feed')
            ->value('source_name'),
    )->toBe('');
});
