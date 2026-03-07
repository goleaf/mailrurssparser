<?php

use App\Models\Article;
use App\Models\RssFeed;
use App\Models\RssParseLog;
use App\Models\Tag;
use App\Services\RssParserService;
use Illuminate\Support\Facades\Http;

function invokeRssMethod(RssParserService $service, string $method, mixed ...$arguments): mixed
{
    $reflection = new ReflectionMethod($service, $method);
    $reflection->setAccessible(true);

    return $reflection->invoke($service, ...$arguments);
}

it('throws when a feed returns a gone status', function () {
    Http::fake([
        '*' => Http::response('Not Found', 404),
    ]);

    $service = new RssParserService;

    expect(fn () => $service->fetchFeedXml('https://example.test/rss'))
        ->toThrow(RuntimeException::class, 'Feed gone: HTTP 404');
});

it('returns feed items limited by config', function () {
    $xml = simplexml_load_string(<<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<rss>
  <channel>
    <item><title>One</title></item>
    <item><title>Two</title></item>
  </channel>
</rss>
XML, 'SimpleXMLElement', LIBXML_NOCDATA);

    config(['rss.parser.max_items_per_feed' => 1]);

    $items = (new RssParserService)->getFeedItems($xml);

    expect($items)->toHaveCount(1)
        ->and((string) $items[0]->title)->toBe('One');
});

it('detects duplicates by guid or url including trashed articles', function () {
    Article::factory()->create([
        'source_guid' => 'guid-123',
        'source_url' => 'https://example.test/a',
    ])->delete();

    $service = new RssParserService;

    expect(invokeRssMethod($service, 'isDuplicate', 'guid-123', 'https://example.test/other'))->toBeTrue()
        ->and(invokeRssMethod($service, 'isDuplicate', 'other-guid', 'https://example.test/a'))->toBeTrue()
        ->and(invokeRssMethod($service, 'isDuplicate', 'missing', 'https://example.test/missing'))->toBeFalse();
});

it('processes an item into an article and auto-assigns tags', function () {
    $item = simplexml_load_string(<<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<item>
  <title>Срочно: Article title</title>
  <link>https://example.test/article</link>
  <description><![CDATA[<p>Hello world</p>]]></description>
  <category>Политика</category>
  <pubDate>Mon, 01 Jan 2024 12:00:00 +0000</pubDate>
</item>
XML, 'SimpleXMLElement', LIBXML_NOCDATA);

    $feed = RssFeed::factory()->create([
        'source_name' => 'Новости Mail',
    ]);

    $service = new RssParserService;

    $article = invokeRssMethod($service, 'processItem', $item, $feed->category_id, $feed->id, $feed);

    expect($article)->toBeInstanceOf(Article::class)
        ->and($article->title)->toBe('Срочно: Article title')
        ->and($article->source_url)->toBe('https://example.test/article')
        ->and($article->rss_feed_id)->toBe($feed->id)
        ->and($article->importance)->toBeGreaterThanOrEqual(5)
        ->and($article->tags)->toHaveCount(1)
        ->and(Tag::query()->where('name', 'Политика')->exists())->toBeTrue();
});

it('inspects a feed without saving articles', function () {
    Http::fake([
        '*' => Http::response(<<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<rss>
  <channel>
    <item>
      <title>First</title>
      <link>https://example.test/1</link>
    </item>
    <item>
      <title>Second</title>
      <link>https://example.test/2</link>
    </item>
  </channel>
</rss>
XML, 200),
    ]);

    Article::factory()->create(['source_url' => 'https://example.test/2']);
    $feed = RssFeed::factory()->create();

    $summary = (new RssParserService)->inspectFeed($feed);

    expect($summary)->toMatchArray([
        'feed' => $feed->title,
        'items' => 2,
        'new' => 1,
        'skip' => 1,
    ])
        ->and(Article::query()->where('rss_feed_id', $feed->id)->count())->toBe(0);
});

it('parses a feed, updates counters, and records a parse log', function () {
    Http::fake([
        '*' => Http::response(<<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<rss>
  <channel>
    <item>
      <title>Item One</title>
      <link>https://example.test/item-one</link>
      <description><![CDATA[<p>Body</p>]]></description>
    </item>
    <item>
      <title>Item Two</title>
      <link>https://example.test/item-two</link>
      <description><![CDATA[<p>Body</p>]]></description>
    </item>
  </channel>
</rss>
XML, 200),
    ]);

    $feed = RssFeed::factory()->create([
        'articles_parsed_total' => 5,
    ]);

    $result = (new RssParserService)->parseFeed($feed, 'manual');

    $feed->refresh();
    $log = RssParseLog::query()->where('rss_feed_id', $feed->id)->latest('id')->first();

    expect($result)->toMatchArray([
        'feed' => $feed->title,
        'new' => 2,
        'skip' => 0,
        'errors' => 0,
        'error' => null,
    ])
        ->and($feed->last_parsed_at)->not->toBeNull()
        ->and($feed->last_run_new_count)->toBe(2)
        ->and($feed->last_run_skip_count)->toBe(0)
        ->and($feed->articles_parsed_total)->toBe(7)
        ->and($log)->not->toBeNull()
        ->and($log?->new_count)->toBe(2)
        ->and($log?->triggered_by)->toBe('manual')
        ->and(Article::query()->where('rss_feed_id', $feed->id)->count())->toBe(2);
});

it('parses all active feeds keyed by id', function () {
    Http::fake([
        '*' => Http::response(<<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<rss>
  <channel>
    <item>
      <title>Item</title>
      <link>https://example.test/item</link>
    </item>
  </channel>
</rss>
XML, 200),
    ]);

    $first = RssFeed::factory()->create(['is_active' => true]);
    $second = RssFeed::factory()->create(['is_active' => true]);
    RssFeed::factory()->create(['is_active' => false]);

    $results = (new RssParserService)->parseAllFeeds('api');

    expect($results)->toHaveKeys([$first->id, $second->id])
        ->and($results[$first->id]['feed'])->toBe($first->title)
        ->and($results[$second->id]['feed'])->toBe($second->title);
});
