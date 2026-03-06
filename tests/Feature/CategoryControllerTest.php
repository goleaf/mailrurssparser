<?php

use App\Http\Controllers\Api\CategoryController;
use App\Models\Article;
use App\Models\Category;
use App\Models\RssFeed;
use App\Models\SubCategory;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    if (! trait_exists(Laravel\Scout\Searchable::class)) {
        $this->markTestSkipped('Laravel Scout is not installed.');
    }
});

it('lists active categories with sub categories', function () {
    Route::get('/api/categories', [CategoryController::class, 'index'])->name('api.categories.index');

    $category = Category::factory()->create([
        'slug' => 'politics',
        'name' => 'Politics',
        'is_active' => true,
    ]);

    SubCategory::factory()->create([
        'category_id' => $category->id,
        'name' => 'Local',
        'slug' => 'local',
    ]);

    Article::factory()->create([
        'category_id' => $category->id,
        'status' => 'published',
        'published_at' => now()->subHour(),
    ]);

    $response = $this->getJson('/api/categories');

    $response->assertOk();

    $payload = $response->json('data');

    expect($payload)->toHaveCount(1)
        ->and($payload[0]['slug'])->toBe('politics')
        ->and($payload[0]['article_count'])->toBe(1)
        ->and($payload[0]['sub_categories'])->toHaveCount(1);
});

it('shows a category with feeds', function () {
    Route::get('/api/categories/{slug}', [CategoryController::class, 'show'])->name('api.categories.show');

    $category = Category::factory()->create([
        'slug' => 'economics',
        'name' => 'Economics',
    ]);

    RssFeed::factory()->create([
        'category_id' => $category->id,
        'title' => 'Economics Feed',
    ]);

    $response = $this->getJson('/api/categories/'.$category->slug);

    $response->assertOk();

    $payload = $response->json('data');

    expect($payload['slug'])->toBe('economics')
        ->and($payload['rss_feeds'])->toHaveCount(1);
});

it('returns category articles with filters', function () {
    Route::get('/api/categories/{slug}/articles', [CategoryController::class, 'articles'])->name('api.categories.articles');

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

    $response = $this->getJson('/api/categories/sport/articles?per_page=10');

    $response->assertOk();

    $payload = $response->json();
    $items = $payload['data']['data'] ?? $payload['data'] ?? [];

    expect($items)->toHaveCount(1);
});
