<?php

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
