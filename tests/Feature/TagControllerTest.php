<?php

use App\Models\Article;
use App\Models\Category;
use App\Models\Tag;

beforeEach(function () {
    if (! trait_exists(Laravel\Scout\Searchable::class)) {
        $this->markTestSkipped('Laravel Scout is not installed.');
    }
});

it('lists tags ordered by usage count', function () {
    $low = Tag::factory()->create(['name' => 'Low', 'usage_count' => 1]);
    $high = Tag::factory()->create(['name' => 'High', 'usage_count' => 10]);

    $response = $this->getJson('/api/v1/tags');

    $response->assertOk();

    $payload = $response->json('data');

    expect($payload[0]['name'])->toBe('High')
        ->and($payload[0]['id'])->toBe($high->id)
        ->and($payload)->toHaveCount(2);
});

it('supports trending and limit query parameters for tags', function () {
    Tag::factory()->create(['name' => 'Regular', 'usage_count' => 40, 'is_trending' => false]);
    $trending = Tag::factory()->create(['name' => 'Trending', 'usage_count' => 30, 'is_trending' => true]);

    $response = $this->getJson('/api/v1/tags?trending=1&limit=1');

    $response->assertOk();

    $payload = $response->json('data');

    expect($payload)->toHaveCount(1)
        ->and($payload[0]['name'])->toBe($trending->name);
});

it('shows tag details with article count', function () {
    $tag = Tag::factory()->create(['slug' => 'featured']);

    $response = $this->getJson('/api/v1/tags/'.$tag->slug);

    $response->assertOk();

    $payload = $response->json('data');

    expect($payload['slug'])->toBe('featured')
        ->and($payload['id'])->toBe($tag->id)
        ->and($payload)->toHaveKey('article_count');
});

it('returns tag articles', function () {
    $category = Category::factory()->create();
    $tag = Tag::factory()->create(['slug' => 'breaking']);

    $article = Article::factory()->create([
        'category_id' => $category->id,
        'status' => 'published',
        'published_at' => now()->subHour(),
    ]);

    $article->tags()->attach($tag);

    $response = $this->getJson('/api/v1/tags/breaking/articles?per_page=10');

    $response->assertOk();

    $payload = $response->json();
    $items = $payload['data']['data'] ?? $payload['data'] ?? [];

    expect($items)->toHaveCount(1);
});
