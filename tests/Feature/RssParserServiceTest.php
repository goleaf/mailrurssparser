<?php

use App\Models\Article;
use App\Models\RssFeed;
use App\Models\RssParseLog;
use App\Models\Tag;
use App\Services\RssParserService;
use Carbon\Carbon;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Monolog\Formatter\LineFormatter;

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

it('follows redirects manually before parsing the xml', function () {
    Http::fake([
        'https://example.test/rss' => Http::response('', 302, [
            'Location' => 'https://example.test/redirected',
        ]),
        'https://example.test/redirected' => Http::response(<<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<rss>
  <channel>
    <item><title>Redirected item</title></item>
  </channel>
</rss>
XML, 200),
    ]);

    $xml = (new RssParserService)->fetchFeedXml('https://example.test/rss');

    expect((string) $xml->channel->item->title)->toBe('Redirected item');

    Http::assertSentCount(2);
});

it('logs feed responses through the after response callback', function () {
    $logPath = storage_path('framework/testing/rss-after-response.log');

    File::ensureDirectoryExists(dirname($logPath));
    File::delete($logPath);

    config([
        'logging.channels.rss' => [
            'driver' => 'single',
            'path' => $logPath,
            'level' => 'debug',
            'formatter' => LineFormatter::class,
            'formatter_with' => [
                'format' => "[%datetime%] %level_name%: %message% %context%\n",
            ],
            'replace_placeholders' => true,
        ],
    ]);

    Http::fake([
        '*' => Http::response(<<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<rss>
  <channel>
    <item><title>Observed item</title></item>
  </channel>
</rss>
XML, 200),
    ]);

    $xml = (new RssParserService)->fetchFeedXml('https://example.test/rss');

    expect((string) $xml->channel->item->title)->toBe('Observed item')
        ->and(File::exists($logPath))->toBeTrue()
        ->and(File::get($logPath))->toContain('RSS response received.')
        ->and(File::get($logPath))->toContain('"url":"https://example.test/rss"')
        ->and(File::get($logPath))->toContain('"status":200');

    File::delete($logPath);
});

it('detects and converts windows-1251 feeds before parsing', function () {
    if (! function_exists('iconv')) {
        $this->markTestSkipped('iconv is not available.');
    }

    $xml = <<<'XML'
<?xml version="1.0" encoding="Windows-1251"?>
<rss>
  <channel>
    <item><title>Политика</title></item>
  </channel>
</rss>
XML;

    $body = iconv('UTF-8', 'Windows-1251//IGNORE', $xml);

    if ($body === false) {
        $this->markTestSkipped('Unable to convert test fixture to Windows-1251.');
    }

    Http::fake([
        '*' => Http::response($body, 200),
    ]);

    $parsedXml = (new RssParserService)->fetchFeedXml('https://example.test/rss');

    expect((string) $parsedXml->channel->item->title)->toBe('Политика');
});

it('throws when the xml does not contain a channel element', function () {
    Http::fake([
        '*' => Http::response(<<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<rss>
  <item><title>Missing channel</title></item>
</rss>
XML, 200),
    ]);

    expect(fn () => (new RssParserService)->fetchFeedXml('https://example.test/rss'))
        ->toThrow(RuntimeException::class, 'Invalid RSS: no channel element');
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

it('extracts item fields from rss namespaces and helpers', function () {
    $item = simplexml_load_string(<<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<item xmlns:content="http://purl.org/rss/1.0/modules/content/" xmlns:media="http://search.yahoo.com/mrss/" xmlns:dc="http://purl.org/dc/elements/1.1/">
  <title>Главная тема — Mail.ru</title>
  <guid isPermaLink="true">https://example.test/from-guid</guid>
  <description><![CDATA[<p>Fallback description <img src="https://example.test/fallback.jpg"></p>]]></description>
  <content:encoded><![CDATA[<p>Main <strong>content</strong><script>alert(1)</script></p>]]></content:encoded>
  <media:content medium="image" url="https://example.test/main.jpg" />
  <media:thumbnail url="https://example.test/thumb.jpg" />
  <author>editor@example.test John Doe</author>
  <dc:date>01.02.2024 14:30</dc:date>
  <category>Политика</category>
  <category>Экономика</category>
</item>
XML, 'SimpleXMLElement', LIBXML_NOCDATA);

    $service = new RssParserService;
    $publishedAt = invokeRssMethod($service, 'extractPubDate', $item);

    expect(invokeRssMethod($service, 'extractTitle', $item))->toBe('Главная тема')
        ->and(invokeRssMethod($service, 'extractLink', $item))->toBe('https://example.test/from-guid')
        ->and(invokeRssMethod($service, 'extractGuid', $item))->toBe('https://example.test/from-guid')
        ->and(invokeRssMethod($service, 'extractDescription', $item))->toBe('Main content')
        ->and(invokeRssMethod($service, 'extractFullHtml', $item))->toBe('<p>Main <strong>content</strong></p>')
        ->and(invokeRssMethod($service, 'extractImage', $item))->toBe('https://example.test/main.jpg')
        ->and(invokeRssMethod($service, 'extractAuthor', $item))->toBe('John Doe')
        ->and($publishedAt)->toBeInstanceOf(Carbon::class)
        ->and($publishedAt->format('d.m.Y H:i'))->toBe('01.02.2024 14:30')
        ->and(invokeRssMethod($service, 'extractCategories', $item))->toBe(['Политика', 'Экономика']);
});

it('generates transliterated unique slugs and content helpers', function () {
    Article::factory()->create([
        'slug' => 'politika-dnya',
    ]);

    $service = new RssParserService;
    $slug = invokeRssMethod($service, 'generateUniqueSlug', 'Политика дня');
    $readingTime = invokeRssMethod($service, 'calculateReadingTime', str_repeat('word ', 250));
    $shortDescription = invokeRssMethod($service, 'makeShortDescription', "  <p>Hello   world</p>\n\n", 20);

    expect($slug)->toBe('politika-dnya-2')
        ->and($readingTime)->toBe(2)
        ->and($shortDescription)->toBe('Hello world');
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

it('builds article data using feed extra settings overrides', function () {
    $item = simplexml_load_string(<<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<item>
  <title>Override test</title>
  <link>https://example.test/override</link>
  <description><![CDATA[<p>Body text for override test</p>]]></description>
</item>
XML, 'SimpleXMLElement', LIBXML_NOCDATA);

    $feed = RssFeed::factory()->make([
        'source_name' => 'Новости Mail',
        'auto_publish' => false,
        'extra_settings' => [
            'status' => 'pending',
            'content_type' => 'analysis',
            'source_name' => 'Custom Source',
            'default_author' => 'Feed Author',
            'short_description_length' => 10,
        ],
    ]);

    $data = invokeRssMethod(new RssParserService, 'buildArticleData', $item, 1, 1, $feed);

    expect($data['status'])->toBe('pending')
        ->and($data['content_type'])->toBe('analysis')
        ->and($data['source_name'])->toBe('Custom Source')
        ->and($data['author'])->toBe('Feed Author')
        ->and($data['short_description'])->toBe('Body text...');
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

it('parses only feeds due for parsing', function () {
    Http::fake([
        '*' => Http::response(<<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<rss>
  <channel>
    <item>
      <title>Due Item</title>
      <link>https://example.test/due-item</link>
    </item>
  </channel>
</rss>
XML, 200),
    ]);

    $dueFeed = RssFeed::factory()->create([
        'is_active' => true,
        'next_parse_at' => now()->subMinute(),
    ]);
    $futureFeed = RssFeed::factory()->create([
        'is_active' => true,
        'next_parse_at' => now()->addHour(),
    ]);
    $inactiveFeed = RssFeed::factory()->create([
        'is_active' => false,
        'next_parse_at' => now()->subMinute(),
    ]);

    $results = (new RssParserService)->parseDueFeeds();

    expect($results)->toHaveKeys([$dueFeed->id])
        ->and($results)->not->toHaveKey($futureFeed->id)
        ->and($results)->not->toHaveKey($inactiveFeed->id);
});

it('imports the first feed item from a url as a draft article', function () {
    Http::fake([
        'https://example.test/import.xml' => Http::response(<<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<rss>
  <channel>
    <item>
      <title>Imported article</title>
      <link>https://example.test/imported-article</link>
      <description><![CDATA[<p>Import body text for the CMS resource.</p>]]></description>
      <category>Политика</category>
      <pubDate>Mon, 01 Jan 2024 12:00:00 +0000</pubDate>
    </item>
  </channel>
</rss>
XML, 200),
    ]);

    $feed = RssFeed::factory()->create([
        'url' => 'https://example.test/import.xml',
        'auto_publish' => true,
    ]);

    $article = Article::withoutSyncingToSearch(function () use ($feed): Article {
        return (new RssParserService)->importArticleFromUrl($feed->url);
    });

    expect($article)->toBeInstanceOf(Article::class)
        ->and($article->status)->toBe('draft')
        ->and($article->rss_feed_id)->toBe($feed->id)
        ->and($article->title)->toBe('Imported article')
        ->and($article->source_url)->toBe('https://example.test/imported-article')
        ->and($article->tags)->toHaveCount(1)
        ->and($article->tags->first()?->name)->toBe('Политика')
        ->and(Article::query()->whereKey($article->id)->exists())->toBeTrue();
});
