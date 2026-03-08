<?php

use App\Models\Article;
use App\Models\ArticleView;
use App\Models\Category;

beforeEach(function () {
    if (! trait_exists(Laravel\Scout\Searchable::class)) {
        $this->markTestSkipped('Laravel Scout is not installed.');
    }
});

it('filters articles by category', function () {
    $politics = Category::factory()->create(['slug' => 'politics']);
    $sports = Category::factory()->create(['slug' => 'sports']);

    Article::factory()->create([
        'category_id' => $politics->id,
        'status' => 'published',
        'published_at' => now()->subHour(),
    ]);

    Article::factory()->create([
        'category_id' => $sports->id,
        'status' => 'published',
        'published_at' => now()->subHour(),
    ]);

    $response = $this->getJson(route('api.v1.articles.index', [
        'category' => 'politics',
        'per_page' => 10,
    ]));

    $response->assertOk();

    $payload = $response->json();
    $items = $payload['data']['data'] ?? $payload['data'] ?? [];

    expect($items)->toHaveCount(1)
        ->and($items[0]['category']['slug'])->toBe('politics');
});

it('increments views when showing an article', function () {
    $category = Category::factory()->create();

    $article = Article::factory()->create([
        'category_id' => $category->id,
        'status' => 'published',
        'published_at' => now()->subHour(),
        'views_count' => 0,
    ]);

    $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.10'])
        ->getJson(route('api.v1.articles.show', ['slug' => $article->slug]))
        ->assertOk();

    expect($article->refresh()->views_count)->toBe(1)
        ->and(ArticleView::query()->where('article_id', $article->id)->count())->toBe(1);
});
