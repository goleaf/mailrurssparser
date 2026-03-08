<?php

use App\Models\Article;
use App\Models\Metric;
use App\Models\NewsletterSubscriber;
use App\Models\RssFeed;
use App\Services\MetricReportService;
use App\Services\MetricTracker;
use App\Services\RssParserService;
use App\Services\TrackedMetric;
use Illuminate\Support\Facades\Http;

it('records article view metrics once per unique hourly view', function () {
    $article = Article::factory()->create(['views_count' => 0]);

    $article->incrementViews('203.0.113.10', 'session-1');
    $article->incrementViews('203.0.113.10', 'session-1');
    $article->incrementViews('203.0.113.11', 'session-2');

    expect(Metric::query()
        ->where('name', TrackedMetric::ArticleView->value)
        ->where('measurable_type', $article->getMorphClass())
        ->where('measurable_id', $article->id)
        ->sum('value'))->toBe(2)
        ->and(Metric::query()
            ->where('name', TrackedMetric::ArticleUniqueView->value)
            ->where('measurable_type', $article->getMorphClass())
            ->where('measurable_id', $article->id)
            ->sum('value'))->toBe(2);
});

it('records bookmark and newsletter lifecycle metrics through the api', function () {
    $article = Article::factory()->create([
        'status' => 'published',
        'published_at' => now()->subHour(),
        'bookmarks_count' => 0,
    ]);
    $request = $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])
        ->withHeader('User-Agent', 'Metric Agent');

    $request->postJson('/api/v1/bookmarks/'.$article->id)->assertSuccessful();
    $request->postJson('/api/v1/bookmarks/'.$article->id)->assertSuccessful();

    $this->postJson('/api/v1/newsletter/subscribe', [
        'email' => 'metrics@example.com',
        'name' => 'Metrics Reader',
    ])->assertSuccessful();

    $subscriber = NewsletterSubscriber::query()->where('email', 'metrics@example.com')->firstOrFail();

    $this->getJson('/api/v1/newsletter/confirm/'.$subscriber->token)->assertSuccessful();
    $this->getJson('/api/v1/newsletter/unsubscribe/'.$subscriber->token)->assertSuccessful();

    expect(Metric::query()->where('name', TrackedMetric::BookmarkAdded->value)->sum('value'))->toBe(1)
        ->and(Metric::query()->where('name', TrackedMetric::BookmarkRemoved->value)->sum('value'))->toBe(1)
        ->and(Metric::query()->where('name', TrackedMetric::NewsletterSubscription->value)->sum('value'))->toBe(1)
        ->and(Metric::query()->where('name', TrackedMetric::NewsletterConfirmation->value)->sum('value'))->toBe(1)
        ->and(Metric::query()->where('name', TrackedMetric::NewsletterUnsubscription->value)->sum('value'))->toBe(1);
});

it('records rss parse metrics for successful and failed runs', function () {
    Http::fake([
        'https://example.test/success.xml' => Http::response(<<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<rss>
  <channel>
    <item>
      <title>Metric Item</title>
      <link>https://example.test/item</link>
    </item>
  </channel>
</rss>
XML, 200),
        'https://example.test/failed.xml' => Http::response('Not Found', 404),
    ]);

    $successFeed = RssFeed::factory()->create([
        'url' => 'https://example.test/success.xml',
    ]);
    $failedFeed = RssFeed::factory()->create([
        'url' => 'https://example.test/failed.xml',
    ]);
    $service = new RssParserService;

    $service->parseFeed($successFeed, 'manual');
    $service->parseFeed($failedFeed, 'manual');

    expect(Metric::query()->where('name', TrackedMetric::RssParseRun->value)->sum('value'))->toBe(2)
        ->and(Metric::query()->where('name', TrackedMetric::RssArticleImported->value)->sum('value'))->toBe(1)
        ->and(Metric::query()->where('name', TrackedMetric::RssParseFailure->value)->sum('value'))->toBe(1);
});

it('returns tracked metric summaries timelines and rankings', function () {
    $article = Article::factory()->create([
        'title' => 'Metrics Story',
        'slug' => 'metrics-story',
    ]);
    $feed = RssFeed::factory()->create([
        'title' => 'Metrics Feed',
    ]);
    $tracker = app(MetricTracker::class);

    $tracker->record(TrackedMetric::ArticleView, 5, $article, now()->subHours(2));
    $tracker->record(TrackedMetric::ArticleUniqueView, 3, $article, now()->subHour());
    $tracker->record(TrackedMetric::BookmarkAdded, 2, $article, now()->subHour());
    $tracker->record(TrackedMetric::NewsletterSubscription, 4, recordedAt: now()->subHours(3));
    $tracker->record(TrackedMetric::NewsletterConfirmation, 3, recordedAt: now()->subHours(2));
    $tracker->record(TrackedMetric::NewsletterUnsubscription, 1, recordedAt: now()->subHour());
    $tracker->record(TrackedMetric::RssArticleImported, 6, $feed, now()->subHours(4));
    $tracker->record(TrackedMetric::RssParseFailure, 1, $feed, now()->subHours(4));

    $this->getJson('/api/v1/stats/metrics')
        ->assertSuccessful()
        ->assertHeaderContains('Cache-Control', 'public')
        ->assertHeaderContains('Cache-Control', 'max-age=60')
        ->assertJsonPath('summary.article_views_24h', 5)
        ->assertJsonPath('summary.unique_article_views_24h', 3)
        ->assertJsonPath('summary.bookmarks_added_24h', 2)
        ->assertJsonPath('summary.rss_imports_24h', 6)
        ->assertJsonPath('summary.rss_failures_24h', 1)
        ->assertJsonPath('summary.newsletter_subscriptions_7d', 4)
        ->assertJsonPath('summary.newsletter_confirmations_7d', 3)
        ->assertJsonPath('summary.newsletter_unsubscriptions_7d', 1)
        ->assertJsonPath('timeline.hours', 24)
        ->assertJsonPath('timeline.series.0.key', TrackedMetric::ArticleView->value)
        ->assertJsonPath('timeline.series.0.total', 5)
        ->assertJsonPath('top_articles.views.0.id', $article->id)
        ->assertJsonPath('top_articles.views.0.views_7d', 5)
        ->assertJsonPath('top_articles.bookmarks.0.id', $article->id)
        ->assertJsonPath('top_articles.bookmarks.0.bookmarks_7d', 2)
        ->assertJsonPath('top_feeds.imports.0.id', $feed->id)
        ->assertJsonPath('top_feeds.imports.0.imports_7d', 6)
        ->assertJsonPath('top_feeds.failures.0.id', $feed->id)
        ->assertJsonPath('top_feeds.failures.0.failures_7d', 1);
});

it('builds keyed totals and timeline buckets from derived pluck closures', function () {
    $article = Article::factory()->create([
        'title' => 'Closure Metric Story',
        'slug' => 'closure-metric-story',
    ]);
    $tracker = app(MetricTracker::class);
    $service = app(MetricReportService::class);

    $tracker->record(TrackedMetric::ArticleView, 2, $article, now()->subHours(2));
    $tracker->record(TrackedMetric::ArticleView, 3, $article, now()->subHour());
    $tracker->record(TrackedMetric::BookmarkAdded, 4, $article, now()->subHour());

    $totals = $service->totals([
        TrackedMetric::ArticleView,
        TrackedMetric::BookmarkAdded,
        TrackedMetric::RssParseFailure,
    ], 3);
    $timeline = $service->timeline([
        TrackedMetric::ArticleView,
        TrackedMetric::BookmarkAdded,
    ], 3);

    expect($totals)->toBe([
        TrackedMetric::ArticleView->value => 5,
        TrackedMetric::BookmarkAdded->value => 4,
        TrackedMetric::RssParseFailure->value => 0,
    ])
        ->and($timeline['labels'])->toHaveCount(3)
        ->and($timeline['series'][0]['key'])->toBe(TrackedMetric::ArticleView->value)
        ->and($timeline['series'][0]['data'])->toBe([2, 3, 0])
        ->and($timeline['series'][1]['key'])->toBe(TrackedMetric::BookmarkAdded->value)
        ->and($timeline['series'][1]['data'])->toBe([0, 4, 0]);
});
