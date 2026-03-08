<?php

use App\Models\Article;
use App\Models\Category;
use App\Models\RssFeed;
use App\Models\SubCategory;

beforeEach(function () {
    if (! trait_exists(Laravel\Scout\Searchable::class)) {
        $this->markTestSkipped('Laravel Scout is not installed.');
    }
});

it('lists active categories with sub categories', function () {
    $category = Category::factory()->create([
        'slug' => 'politics',
        'name' => 'Politics',
        'is_active' => true,
    ]);

    $subCategory = SubCategory::factory()->create([
        'category_id' => $category->id,
        'name' => 'Local',
        'slug' => 'local',
    ]);

    Article::factory()->create([
        'category_id' => $category->id,
        'status' => 'published',
        'published_at' => now()->subHour(),
    ]);

    $response = $this->getJson('/api/v1/categories');

    $response->assertOk()
        ->assertHeaderContains('Cache-Control', 'public')
        ->assertHeaderContains('Cache-Control', 'max-age=900')
        ->assertHeaderContains('Cache-Control', 'stale-while-revalidate=2700');

    $payload = $response->json('data');

    expect($payload)->toHaveCount(1)
        ->and($payload[0]['slug'])->toBe('politics')
        ->and($payload[0]['id'])->toBe($category->id)
        ->and($payload[0]['sub_categories'])->toHaveCount(1)
        ->and($payload[0]['sub_categories'][0]['id'])->toBe($subCategory->id);
});

it('shows a category with feeds', function () {
    $category = Category::factory()->create([
        'slug' => 'economics',
        'name' => 'Economics',
    ]);

    $feed = RssFeed::factory()->create([
        'category_id' => $category->id,
        'title' => 'Economics Feed',
    ]);

    $response = $this->getJson('/api/v1/categories/'.$category->slug);

    $response->assertOk();

    $payload = $response->json('data');

    expect($payload['slug'])->toBe('economics')
        ->and($payload['id'])->toBe($category->id)
        ->and($payload['rss_feeds'])->toHaveCount(1)
        ->and($payload['rss_feeds'][0]['id'])->toBe($feed->id);
});

it('returns category articles with filters', function () {
    $category = Category::factory()->create(['slug' => 'sport']);
    $other = Category::factory()->create(['slug' => 'other']);

    Article::factory()->create([
        'category_id' => $category->id,
        'status' => 'published',
        'published_at' => now()->subHour(),
    ]);

    Article::factory()->create([
        'category_id' => $other->id,
        'status' => 'published',
        'published_at' => now()->subHour(),
    ]);

    $response = $this->getJson('/api/v1/categories/sport/articles?per_page=10');

    $response->assertOk();

    $payload = $response->json();
    $items = $payload['data']['data'] ?? $payload['data'] ?? [];

    expect($items)->toHaveCount(1);
});
