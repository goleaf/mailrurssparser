<?php

use App\Mail\ConfirmSubscriptionMail;
use App\Models\Article;
use App\Models\ArticleView;
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

    $this->withHeaders([
        'User-Agent' => 'Pest Browser',
        'CF-IPCountry' => 'DE',
        'X-Timezone' => 'Europe/Berlin',
        'X-Locale' => 'de-DE',
    ])
        ->getJson('/api/v1/articles/'.$article->slug)
        ->assertSuccessful()
        ->assertJsonPath('data.slug', $article->slug)
        ->assertJsonStructure([
            'data' => [
                'related_ids',
                'related_articles',
                'similar_articles',
                'more_from_category',
            ],
        ]);

    expect($article->views()->count())->toBe(1)
        ->and($article->views()->first()?->ip_hash)->not->toBeNull()
        ->and($article->views()->first()?->country_code)->toBe('DE')
        ->and($article->views()->first()?->timezone)->toBe('Europe/Berlin')
        ->and($article->views()->first()?->locale)->toBe('de');
});

it('classifies internal referrers using URI authority parsing', function () {
    config()->set('app.url', 'https://news.test:8443');

    $article = Article::factory()->create([
        'status' => 'published',
        'published_at' => now()->subHour(),
    ]);

    $this->withHeaders([
        'User-Agent' => 'Pest Browser',
        'Referer' => 'https://news.test:8443/#/articles/source-story',
    ])->getJson('/api/v1/articles/'.$article->slug)
        ->assertSuccessful();

    $view = $article->fresh()->views()->latest('id')->first();

    expect($view?->referrer_type)->toBe('internal')
        ->and($view?->referrer_domain)->toBe('news.test');
});

it('classifies malformed referrers as other when they do not contain a scheme', function () {
    $article = Article::factory()->create([
        'status' => 'published',
        'published_at' => now()->subHour(),
    ]);

    $this->withHeaders([
        'User-Agent' => 'Pest Browser',
        'Referer' => 'news.test/#/articles/source-story',
    ])->getJson('/api/v1/articles/'.$article->slug)
        ->assertSuccessful();

    $view = $article->fresh()->views()->latest('id')->first();

    expect($view?->referrer_type)->toBe('other')
        ->and($view?->referrer_domain)->toBeNull();
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
    $article = Article::query()->firstOrFail();

    ArticleView::factory()->count(2)->create([
        'article_id' => $article->id,
        'country_code' => 'DE',
        'timezone' => 'Europe/Berlin',
        'viewed_at' => now()->subHour(),
    ]);
    ArticleView::factory()->create([
        'article_id' => $article->id,
        'country_code' => 'FR',
        'timezone' => 'Europe/Paris',
        'viewed_at' => now()->subHour(),
    ]);

    $this->getJson('/api/v1/stats/overview')
        ->assertSuccessful()
        ->assertJsonStructure([
            'articles' => ['total', 'today', 'this_week', 'breaking', 'featured'],
            'views' => ['total', 'today', 'this_week', 'unique_today'],
            'top_countries' => [
                '*' => ['country_code', 'view_count'],
            ],
            'top_timezones' => [
                '*' => ['timezone', 'view_count'],
            ],
            'feeds' => ['total', 'active', 'errors'],
        ])
        ->assertJsonPath('top_countries.0.country_code', 'DE')
        ->assertJsonPath('top_countries.0.view_count', 2)
        ->assertJsonPath('top_timezones.0.timezone', 'Europe/Berlin')
        ->assertJsonPath('top_timezones.0.view_count', 2);

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

    $this->withHeaders([
        'CF-IPCountry' => 'PL',
        'X-Timezone' => 'Europe/Warsaw',
        'X-Locale' => 'pl-PL',
    ])->postJson('/api/v1/newsletter/subscribe', [
        'email' => 'reader@example.com',
        'name' => 'Reader',
        'category_ids' => [$category->id],
    ])->assertSuccessful()
        ->assertJson(['success' => true]);

    $subscriber = NewsletterSubscriber::query()->where('email', 'reader@example.com')->first();

    expect($subscriber)->not->toBeNull()
        ->and($subscriber?->country_code)->toBe('PL')
        ->and($subscriber?->timezone)->toBe('Europe/Warsaw')
        ->and($subscriber?->locale)->toBe('pl');

    Mail::assertSent(ConfirmSubscriptionMail::class);
});
