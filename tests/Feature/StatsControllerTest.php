<?php

use App\Http\Controllers\Api\StatsController;
use App\Models\Article;
use App\Models\ArticleView;
use App\Models\Category;
use App\Models\RssFeed;
use App\Models\Tag;
use Illuminate\Support\Facades\Route;

it('validates calendar parameters', function () {
    Route::get('/api/stats/calendar/{year}/{month}', [StatsController::class, 'calendar'])->name('api.stats.calendar');

    $this->getJson('/api/stats/calendar/2010/13')
        ->assertStatus(422);
});

it('returns feeds status data', function () {
    Route::get('/api/stats/feeds', [StatsController::class, 'feedsStatus'])->name('api.stats.feeds');

    $category = Category::factory()->create(['name' => 'News']);
    RssFeed::factory()->create([
        'category_id' => $category->id,
        'title' => 'Main Feed',
    ]);

    $response = $this->getJson('/api/stats/feeds');

    $response->assertOk();

    $payload = $response->json('data');

    expect($payload)->toHaveCount(1)
        ->and($payload[0]['category_name'])->toBe('News');
});

it('returns overview stats', function () {
    if (! trait_exists(Laravel\Scout\Searchable::class)) {
        $this->markTestSkipped('Laravel Scout is not installed.');
    }

    Route::get('/api/stats/overview', [StatsController::class, 'overview'])->name('api.stats.overview');

    $category = Category::factory()->create();
    $tag = Tag::factory()->create();
    $feed = RssFeed::factory()->create(['category_id' => $category->id]);

    $article = Article::factory()->create([
        'category_id' => $category->id,
        'rss_feed_id' => $feed->id,
        'status' => 'published',
        'published_at' => today(),
        'views_count' => 5,
    ]);

    $article->tags()->attach($tag);

    ArticleView::factory()->create([
        'article_id' => $article->id,
        'viewed_at' => today(),
    ]);

    $response = $this->getJson('/api/stats/overview');

    $response->assertOk()
        ->assertJsonFragment(['total_articles' => 1])
        ->assertJsonFragment(['today_articles' => 1])
        ->assertJsonFragment(['total_views' => 5]);
});
