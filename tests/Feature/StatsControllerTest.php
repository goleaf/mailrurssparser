<?php

use App\Models\Article;
use App\Models\ArticleView;
use App\Models\Category;
use App\Models\RssFeed;
use App\Models\RssParseLog;
use App\Models\Tag;
use Illuminate\Support\Carbon;

it('validates calendar parameters', function () {
    $this->getJson('/api/v1/stats/calendar/2010/13')
        ->assertUnprocessable();
});

it('returns overview analytics with top categories and tags', function () {
    $category = Category::factory()->create(['name' => 'News', 'slug' => 'news']);
    Category::factory()->create(['name' => 'Ghost', 'slug' => 'ghost']);
    $tag = Tag::factory()->create(['name' => 'Politics', 'usage_count' => 25]);
    Tag::factory()->create(['name' => 'Unused', 'usage_count' => 0]);
    $feed = RssFeed::factory()->create([
        'category_id' => $category->id,
        'title' => 'Main Feed',
        'is_active' => true,
        'last_parsed_at' => now()->subMinutes(10),
    ]);

    $article = Article::factory()->create([
        'category_id' => $category->id,
        'rss_feed_id' => $feed->id,
        'status' => 'published',
        'published_at' => now(),
        'is_breaking' => true,
        'is_featured' => true,
        'views_count' => 5,
    ]);

    $article->tags()->attach($tag);

    ArticleView::factory()->create([
        'article_id' => $article->id,
        'country_code' => 'DE',
        'ip_hash' => 'hash-de-1',
        'timezone' => 'Europe/Berlin',
        'viewed_at' => today(),
    ]);
    ArticleView::factory()->create([
        'article_id' => $article->id,
        'country_code' => 'DE',
        'ip_hash' => 'hash-de-2',
        'timezone' => 'Europe/Berlin',
        'viewed_at' => today(),
    ]);
    ArticleView::factory()->create([
        'article_id' => $article->id,
        'country_code' => 'FR',
        'ip_hash' => 'hash-fr-1',
        'timezone' => 'Europe/Paris',
        'viewed_at' => today(),
    ]);

    $response = $this->getJson('/api/v1/stats/overview');

    $response->assertSuccessful()
        ->assertHeaderContains('Cache-Control', 'public')
        ->assertHeaderContains('Cache-Control', 'max-age=120')
        ->assertHeaderContains('Cache-Control', 'stale-while-revalidate=480')
        ->assertJsonPath('articles.total', 1)
        ->assertJsonPath('articles.today', 1)
        ->assertJsonPath('articles.this_week', 1)
        ->assertJsonPath('articles.breaking', 1)
        ->assertJsonPath('articles.featured', 1)
        ->assertJsonPath('views.total', 5)
        ->assertJsonPath('views.today', 3)
        ->assertJsonPath('views.unique_today', 3)
        ->assertJsonPath('top_countries.0.country_code', 'DE')
        ->assertJsonPath('top_countries.0.view_count', 2)
        ->assertJsonPath('top_timezones.0.timezone', 'Europe/Berlin')
        ->assertJsonPath('top_timezones.0.view_count', 2)
        ->assertJsonPath('top_categories.0.slug', 'news')
        ->assertJsonPath('trending_tags.0.name', 'Politics')
        ->assertJsonPath('last_parse', $feed->last_parsed_at?->toIso8601String())
        ->assertJsonPath('feeds.total', 1)
        ->assertJsonPath('feeds.active', 1)
        ->assertJsonPath('feeds.errors', 0)
        ->assertJsonCount(1, 'top_categories')
        ->assertJsonMissing([
            'name' => 'Unused',
        ]);
});

it('returns chart data grouped for the requested period', function () {
    $politics = Category::factory()->create([
        'name' => 'Politics',
        'slug' => 'politics',
        'color' => '#DC2626',
    ]);
    $sport = Category::factory()->create([
        'name' => 'Sport',
        'slug' => 'sport',
        'color' => '#0891B2',
    ]);

    Article::factory()->create([
        'category_id' => $politics->id,
        'status' => 'published',
        'published_at' => Carbon::parse('2026-03-05 10:00:00'),
        'shares_count' => 2,
    ]);
    Article::factory()->create([
        'category_id' => $sport->id,
        'status' => 'published',
        'published_at' => Carbon::parse('2026-03-06 10:00:00'),
        'shares_count' => 3,
    ]);

    $this->getJson('/api/v1/stats/chart?type=articles&period=30d')
        ->assertSuccessful()
        ->assertJsonPath('period', '30d')
        ->assertJsonStructure([
            'labels',
            'data',
            'period',
            'series' => [
                '*' => ['id', 'name', 'color', 'data'],
            ],
        ]);
});

it('returns popular articles with change percentages', function () {
    $category = Category::factory()->create(['name' => 'News']);
    $article = Article::factory()->create([
        'category_id' => $category->id,
        'status' => 'published',
        'published_at' => now()->subDays(2),
        'shares_count' => 7,
        'bookmarks_count' => 4,
    ]);

    ArticleView::factory()->count(3)->create([
        'article_id' => $article->id,
        'viewed_at' => now()->subDays(2),
    ]);

    ArticleView::factory()->count(2)->create([
        'article_id' => $article->id,
        'viewed_at' => now()->subDays(10),
    ]);

    $this->getJson('/api/v1/stats/popular?period=month&limit=5')
        ->assertSuccessful()
        ->assertJsonPath('data.0.article_id', $article->id)
        ->assertJsonPath('data.0.category', 'News')
        ->assertJsonPath('data.0.view_count', 5)
        ->assertJsonPath('data.0.shares_count', 7)
        ->assertJsonPath('data.0.bookmarks_count', 4)
        ->assertJsonStructure([
            'data' => [
                '*' => ['article_id', 'title', 'slug', 'category', 'view_count', 'shares_count', 'bookmarks_count', 'change_percent'],
            ],
        ]);
});

it('returns calendar day counts for a given month', function () {
    Article::factory()->create([
        'status' => 'published',
        'published_at' => Carbon::parse('2026-03-05 12:00:00'),
    ]);

    $this->getJson('/api/v1/stats/calendar/2026/3')
        ->assertSuccessful()
        ->assertJson([
            '5' => 1,
        ]);
});

it('returns feeds performance with latest run data and averages', function () {
    $category = Category::factory()->create(['name' => 'News']);
    $feed = RssFeed::factory()->create([
        'category_id' => $category->id,
        'title' => 'Main Feed',
    ]);

    Article::factory()->create([
        'category_id' => $category->id,
        'rss_feed_id' => $feed->id,
        'status' => 'published',
        'published_at' => today(),
    ]);

    RssParseLog::factory()->create([
        'rss_feed_id' => $feed->id,
        'started_at' => now()->subHour(),
        'new_count' => 4,
        'skip_count' => 1,
        'error_count' => 0,
        'duration_ms' => 1200,
    ]);

    RssParseLog::factory()->create([
        'rss_feed_id' => $feed->id,
        'started_at' => now()->subMinutes(10),
        'new_count' => 6,
        'skip_count' => 2,
        'error_count' => 1,
        'duration_ms' => 1800,
    ]);

    $this->getJson('/api/v1/stats/feeds')
        ->assertSuccessful()
        ->assertJsonPath('data.0.title', 'Main Feed')
        ->assertJsonPath('data.0.category', 'News')
        ->assertJsonPath('data.0.total_articles', 1)
        ->assertJsonPath('data.0.today_articles_count', 1)
        ->assertJsonPath('data.0.last_run.new_count', 6)
        ->assertJsonPath('data.0.avg_duration_ms', 1500);
});

it('returns category breakdown with percentages and top article', function () {
    $category = Category::factory()->create([
        'name' => 'Politics',
        'slug' => 'politics',
        'color' => '#DC2626',
    ]);

    Category::factory()->create([
        'name' => 'Empty',
        'slug' => 'empty',
        'color' => '#2563EB',
    ]);

    $topArticle = Article::factory()->create([
        'category_id' => $category->id,
        'title' => 'Top Story',
        'slug' => 'top-story',
        'status' => 'published',
        'published_at' => now()->subHour(),
        'views_count' => 100,
    ]);

    Article::factory()->create([
        'category_id' => $category->id,
        'status' => 'published',
        'published_at' => now()->subHours(2),
        'views_count' => 10,
    ]);

    $this->getJson('/api/v1/stats/categories')
        ->assertSuccessful()
        ->assertJsonPath('data.0.slug', 'politics')
        ->assertJsonPath('data.0.article_count', 2)
        ->assertJsonPath('data.0.top_article.slug', $topArticle->slug)
        ->assertJsonCount(1, 'data');
});
