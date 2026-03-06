<?php

use App\Http\Controllers\Api\SearchController;
use App\Models\Article;
use App\Models\Category;
use Illuminate\Support\Facades\Route;

it('validates the search term', function () {
    Route::get('/api/search', [SearchController::class, 'index'])->name('api.search');

    $this->getJson('/api/search')
        ->assertStatus(422);
});

it('returns a search response', function () {
    if (! trait_exists(Laravel\Scout\Searchable::class)) {
        $this->markTestSkipped('Laravel Scout is not installed.');
    }

    Route::get('/api/search', [SearchController::class, 'index'])->name('api.search');

    $category = Category::factory()->create();

    Article::factory()->create([
        'category_id' => $category->id,
        'title' => 'Hello world',
        'short_description' => 'Hello description',
        'status' => 'published',
        'published_at' => now()->subHour(),
    ]);

    $response = $this->getJson('/api/search?q=Hello');

    $response->assertOk();
});
