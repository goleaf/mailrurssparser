<?php

use App\Http\Controllers\Api\TagController;
use App\Models\Article;
use App\Models\Category;
use App\Models\Tag;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    if (! trait_exists(Laravel\Scout\Searchable::class)) {
        $this->markTestSkipped('Laravel Scout is not installed.');
    }
});

it('lists tags ordered by usage count', function () {
    Route::get('/api/tags', [TagController::class, 'index'])->name('api.tags.index');

    Tag::factory()->create(['name' => 'Low', 'usage_count' => 1]);
    Tag::factory()->create(['name' => 'High', 'usage_count' => 10]);

    $response = $this->getJson('/api/tags');

    $response->assertOk();

    $payload = $response->json('data');

    expect($payload[0]['name'])->toBe('High')
        ->and($payload)->toHaveCount(2);
});

it('shows tag details with article count', function () {
    Route::get('/api/tags/{slug}', [TagController::class, 'show'])->name('api.tags.show');

    $tag = Tag::factory()->create(['slug' => 'featured']);

    $response = $this->getJson('/api/tags/'.$tag->slug);

    $response->assertOk();

    $payload = $response->json('data');

    expect($payload['slug'])->toBe('featured')
        ->and($payload)->toHaveKey('article_count');
});

it('returns tag articles', function () {
    Route::get('/api/tags/{slug}/articles', [TagController::class, 'articles'])->name('api.tags.articles');

    $category = Category::factory()->create();
    $tag = Tag::factory()->create(['slug' => 'breaking']);

    $article = Article::factory()->create([
        'category_id' => $category->id,
        'status' => 'published',
        'published_at' => now()->subHour(),
    ]);

    $article->tags()->attach($tag);

    $response = $this->getJson('/api/tags/breaking/articles?per_page=10');

    $response->assertOk();

    $payload = $response->json();
    $items = $payload['data']['data'] ?? $payload['data'] ?? [];

    expect($items)->toHaveCount(1);
});
