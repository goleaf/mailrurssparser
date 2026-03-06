<?php

use App\Models\Article;
use App\Models\RssFeed;
use App\Services\RssParserService;
use Illuminate\Support\Facades\Http;

function invokeFetchFeedXml(RssParserService $service, string $url): SimpleXMLElement
{
    $method = new ReflectionMethod($service, 'fetchFeedXml');
    $method->setAccessible(true);

    return $method->invoke($service, $url);
}

function invokePrivateMethod(RssParserService $service, string $method, mixed ...$arguments): mixed
{
    $reflection = new ReflectionMethod($service, $method);
    $reflection->setAccessible(true);

    return $reflection->invoke($service, ...$arguments);
}

it('throws when the response is not successful', function () {
    Http::fake([
        '*' => Http::response('Not Found', 404),
    ]);

    $service = new RssParserService;

    expect(fn () => invokeFetchFeedXml($service, 'https://example.test/rss'))
        ->toThrow(RuntimeException::class, 'Feed fetch failed: HTTP 404 for https://example.test/rss');
});

it('throws when xml is invalid', function () {
    Http::fake([
        '*' => Http::response('<rss><channel>', 200),
    ]);

    $service = new RssParserService;

    expect(fn () => invokeFetchFeedXml($service, 'https://example.test/rss'))
        ->toThrow(RuntimeException::class);
});

it('throws when channel is missing', function () {
    Http::fake([
        '*' => Http::response('<?xml version="1.0"?><rss></rss>', 200),
    ]);

    $service = new RssParserService;

    expect(fn () => invokeFetchFeedXml($service, 'https://example.test/rss'))
        ->toThrow(RuntimeException::class, 'Invalid RSS: no channel element in https://example.test/rss');
});

it('returns feed items limited by config', function () {
    $xmlString = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<rss>
  <channel>
    <title>Test</title>
    <item><title>One</title></item>
    <item><title>Two</title></item>
  </channel>
</rss>
XML;

    $xml = simplexml_load_string($xmlString, 'SimpleXMLElement', LIBXML_NOCDATA);

    config(['rss.parser.max_items_per_feed' => 1]);

    $service = new RssParserService;
    $items = $service->getFeedItems($xml);

    expect($items)->toHaveCount(1)
        ->and((string) $items[0]->title)->toBe('One');
});

it('returns an empty array when there are no items', function () {
    $xmlString = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<rss>
  <channel>
    <title>Empty</title>
  </channel>
</rss>
XML;

    $xml = simplexml_load_string($xmlString, 'SimpleXMLElement', LIBXML_NOCDATA);

    $service = new RssParserService;

    expect($service->getFeedItems($xml))->toBe([]);
});

it('detects duplicates by guid or url', function () {
    if (! trait_exists(Laravel\Scout\Searchable::class)) {
        $this->markTestSkipped('Laravel Scout is not installed.');
    }

    $article = App\Models\Article::factory()->create([
        'source_guid' => 'guid-123',
        'source_url' => 'https://example.test/a',
    ]);

    $service = new RssParserService;

    expect(invokePrivateMethod($service, 'isDuplicate', 'guid-123', 'https://example.test/other'))->toBeTrue()
        ->and(invokePrivateMethod($service, 'isDuplicate', 'other-guid', 'https://example.test/a'))->toBeTrue()
        ->and(invokePrivateMethod($service, 'isDuplicate', 'missing', 'https://example.test/missing'))->toBeFalse();

    $article->delete();
});

it('generates unique slugs', function () {
    if (! trait_exists(Laravel\Scout\Searchable::class)) {
        $this->markTestSkipped('Laravel Scout is not installed.');
    }

    App\Models\Article::factory()->create(['slug' => 'test-slug']);

    $service = new RssParserService;

    $slug = invokePrivateMethod($service, 'generateUniqueSlug', 'test slug');

    expect($slug)->toBe('test-slug-2');
});

it('processes an item into an article', function () {
    if (! trait_exists(Laravel\Scout\Searchable::class)) {
        $this->markTestSkipped('Laravel Scout is not installed.');
    }

    $xmlString = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<item>
  <title>Article title</title>
  <link>https://example.test/article</link>
  <description><![CDATA[<p>Hello world</p>]]></description>
  <pubDate>Mon, 01 Jan 2024 12:00:00 +0000</pubDate>
</item>
XML;

    $item = simplexml_load_string($xmlString, 'SimpleXMLElement', LIBXML_NOCDATA);

    $service = new RssParserService;

    $article = invokePrivateMethod($service, 'processItem', $item, 1, 2);

    expect($article)->not->toBeNull()
        ->and($article->title)->toBe('Article title')
        ->and($article->source_url)->toBe('https://example.test/article')
        ->and($article->category_id)->toBe(1)
        ->and($article->rss_feed_id)->toBe(2);
});

it('parses a feed and updates counters', function () {
    if (! trait_exists(Laravel\Scout\Searchable::class)) {
        $this->markTestSkipped('Laravel Scout is not installed.');
    }

    Http::fake(function ($request) {
        $url = $request->url();
        $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<rss>
  <channel>
    <title>Test</title>
    <item>
      <title>Item for {$url}</title>
      <link>{$url}/item</link>
      <description><![CDATA[<p>Body</p>]]></description>
    </item>
  </channel>
</rss>
XML;

        return Http::response($xml, 200);
    });

    $feed = RssFeed::factory()->create([
        'articles_parsed_total' => 5,
    ]);

    $service = new RssParserService;

    $result = $service->parseFeed($feed);

    $feed->refresh();

    expect($result['new'])->toBe(1)
        ->and($result['skipped'])->toBe(0)
        ->and($result['errors'])->toBe(0)
        ->and($feed->last_parsed_at)->not->toBeNull()
        ->and($feed->last_run_new_count)->toBe(1)
        ->and($feed->last_run_skip_count)->toBe(0)
        ->and($feed->articles_parsed_total)->toBe(6)
        ->and(Article::query()->where('rss_feed_id', $feed->id)->count())->toBe(1);
});

it('parses all active feeds and returns results keyed by id', function () {
    if (! trait_exists(Laravel\Scout\Searchable::class)) {
        $this->markTestSkipped('Laravel Scout is not installed.');
    }

    Http::fake(function ($request) {
        $url = $request->url();
        $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<rss>
  <channel>
    <title>Test</title>
    <item>
      <title>Item for {$url}</title>
      <link>{$url}/item</link>
      <description><![CDATA[<p>Body</p>]]></description>
    </item>
  </channel>
</rss>
XML;

        return Http::response($xml, 200);
    });

    $first = RssFeed::factory()->create(['is_active' => true]);
    $second = RssFeed::factory()->create(['is_active' => true]);
    RssFeed::factory()->create(['is_active' => false]);

    $service = new RssParserService;

    $results = $service->parseAllFeeds();

    expect($results)->toHaveKeys([$first->id, $second->id]);
});
