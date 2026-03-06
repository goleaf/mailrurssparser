<?php

use App\Services\RssParserService;
use Illuminate\Support\Facades\Http;

function invokeFetchFeedXml(RssParserService $service, string $url): SimpleXMLElement
{
    $method = new ReflectionMethod($service, 'fetchFeedXml');
    $method->setAccessible(true);

    return $method->invoke($service, $url);
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
