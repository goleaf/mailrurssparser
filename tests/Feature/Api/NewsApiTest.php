<?php

use App\Mail\ConfirmSubscriptionMail;
use App\Models\Article;
use App\Models\Bookmark;
use App\Models\Category;
use App\Models\NewsletterSubscriber;
use App\Models\RssFeed;
use App\Models\Tag;
use Illuminate\Support\Facades\Mail;

it('returns paginated articles from the v1 index endpoint', function () {
    $category = Category::factory()->create(['slug' => 'main']);
    Article::factory()->count(3)->create([
        'category_id' => $category->id,
        'status' => 'published',
        'published_at' => now()->subHour(),
    ]);

    $response = $this->getJson('/api/v1/articles');

    $response->assertSuccessful()
        ->assertJsonStructure([
            'data',
            'links',
            'meta' => ['current_page', 'total', 'categories_summary', 'total_results'],
        ]);
});

it('shows a single article and records a hashed view', function () {
    $article = Article::factory()->create([
        'status' => 'published',
        'published_at' => now()->subHour(),
    ]);

    $this->withHeader('User-Agent', 'Pest Browser')
        ->getJson('/api/v1/articles/'.$article->slug)
        ->assertSuccessful()
        ->assertJsonPath('data.slug', $article->slug);

    expect($article->views()->count())->toBe(1)
        ->and($article->views()->first()?->ip_hash)->not->toBeNull();
});

it('supports bookmark toggling for the current session fingerprint', function () {
    $article = Article::factory()->create();

    $this->withHeader('User-Agent', 'Bookmark Agent')
        ->postJson('/api/v1/bookmarks/'.$article->id)
        ->assertSuccessful()
        ->assertJson(['bookmarked' => true]);

    expect(Bookmark::query()->where('article_id', $article->id)->count())->toBe(1);

    $this->withHeader('User-Agent', 'Bookmark Agent')
        ->postJson('/api/v1/bookmarks/'.$article->id)
        ->assertSuccessful()
        ->assertJson(['bookmarked' => false]);
});

it('returns search suggestions across articles categories and tags', function () {
    $category = Category::factory()->create(['name' => 'Спорт', 'slug' => 'sport']);
    Tag::factory()->create(['name' => 'Спорт', 'slug' => 'sport']);
    Article::factory()->create([
        'category_id' => $category->id,
        'title' => 'Спорт сегодня',
        'status' => 'published',
        'published_at' => now()->subHour(),
    ]);

    $this->getJson('/api/v1/search/suggest?q=Спорт')
        ->assertSuccessful()
        ->assertJsonStructure([
            'articles',
            'categories',
            'tags',
        ]);
});

it('returns overview stats and feed performance data', function () {
    $feed = RssFeed::factory()->create(['is_active' => true]);
    Article::factory()->create([
        'category_id' => $feed->category_id,
        'rss_feed_id' => $feed->id,
        'status' => 'published',
        'published_at' => now()->subHour(),
    ]);

    $this->getJson('/api/v1/stats/overview')
        ->assertSuccessful()
        ->assertJsonStructure([
            'articles' => ['total', 'today', 'this_week', 'breaking', 'featured'],
            'views' => ['total', 'today', 'this_week', 'unique_today'],
            'feeds' => ['total', 'active', 'errors'],
        ]);

    $this->getJson('/api/v1/stats/feeds')
        ->assertSuccessful()
        ->assertJsonStructure([
            'data' => [
                '*' => ['id', 'title', 'category', 'total_articles', 'today_articles_count', 'avg_duration_ms'],
            ],
        ]);
});

it('subscribes a newsletter recipient and sends confirmation mail', function () {
    Mail::fake();
    $category = Category::factory()->create();

    $this->postJson('/api/v1/newsletter/subscribe', [
        'email' => 'reader@example.com',
        'name' => 'Reader',
        'category_ids' => [$category->id],
    ])->assertSuccessful()
        ->assertJson(['success' => true]);

    expect(NewsletterSubscriber::query()->where('email', 'reader@example.com')->exists())->toBeTrue();

    Mail::assertSent(ConfirmSubscriptionMail::class);
});
