<?php

use App\Models\Article;
use App\Models\Category;
use App\Models\RssFeed;
use App\Models\RssParseLog;
use App\Models\SubCategory;
use App\Models\Tag;
use App\Services\ArticleContentType;
use App\Services\ArticleStatus;
use App\Services\RssParserService;
use Carbon\Carbon;
use Illuminate\Http\Client\Request as HttpClientRequest;
use Illuminate\Http\Client\Response;
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

it('registers tappable http client response macros for redirects', function () {
    Http::fake([
        '*' => Http::response('', 302, [
            'Location' => ' https://example.test/redirected ',
        ]),
    ]);

    $redirectLocation = null;
    $response = Http::withoutRedirecting()->get('https://example.test/rss')
        ->tap(function (Response $response) use (&$redirectLocation): void {
            $redirectLocation = $response->redirectLocation();
        });

    expect($response->isRedirectStatus())->toBeTrue()
        ->and($redirectLocation)->toBe('https://example.test/redirected');
});

it('logs feed responses through the response tap callback', function () {
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
        ->and(File::get($logPath))->toContain('RSS request sending.')
        ->and(File::get($logPath))->toContain('RSS response received.')
        ->and(File::get($logPath))->toContain('"request_type":"rss_feed"')
        ->and(File::get($logPath))->toContain('"original_url":"https://example.test/rss"')
        ->and(File::get($logPath))->toContain('"url":"https://example.test/rss"')
        ->and(File::get($logPath))->toContain('"status":200');

    File::delete($logPath);
});

it('attaches request attributes to outbound feed requests', function () {
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

    (new RssParserService)->fetchFeedXml('https://example.test/rss');

    Http::assertSent(function (HttpClientRequest $request): bool {
        return $request->url() === 'https://example.test/rss'
            && $request->attributes()['request_type'] === 'rss_feed'
            && $request->attributes()['request_url'] === 'https://example.test/rss'
            && $request->attributes()['original_url'] === 'https://example.test/rss'
            && $request->attributes()['attempt'] === 1
            && $request->attributes()['max_retries'] === 3;
    });
});

it('merges reusable http url parameters when fetching feeds with path query and port', function () {
    Http::fake([
        '*' => Http::response(<<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<rss>
  <channel>
    <item><title>Merged params item</title></item>
  </channel>
</rss>
XML, 200),
    ]);

    $xml = (new RssParserService)->fetchFeedXml('https://example.test:8443/news/rss.xml?lang=ru&section=main');

    expect((string) $xml->channel->item->title)->toBe('Merged params item');

    Http::assertSent(function (HttpClientRequest $request): bool {
        return $request->url() === 'https://example.test:8443/news/rss.xml?lang=ru&section=main'
            && $request->attributes()['request_url'] === 'https://example.test:8443/news/rss.xml?lang=ru&section=main';
    });
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
        'source_name' => '',
        'extra_settings' => [
            'sub_category_name' => 'Отрасли',
            'sub_category_slug' => 'otrasli',
        ],
    ]);

    $service = new RssParserService;

    $article = invokeRssMethod($service, 'processItem', $item, $feed->category_id, $feed->id, $feed);

    expect($article)->toBeInstanceOf(Article::class)
        ->and($article->title)->toBe('Срочно: Article title')
        ->and($article->source_url)->toBe('https://example.test/article')
        ->and($article->source_name)->toBeNull()
        ->and($article->rss_feed_id)->toBe($feed->id)
        ->and($article->sub_category_id)->toBe(
            SubCategory::query()
                ->where('category_id', $feed->category_id)
                ->where('slug', 'otrasli')
                ->value('id'),
        )
        ->and($article->importance)->toBeGreaterThanOrEqual(5)
        ->and($article->tags)->toHaveCount(1)
        ->and(Tag::query()->where('name', 'Политика')->exists())->toBeTrue();
});

it('creates subcategories from rss item categories and assigns them to articles', function () {
    $category = Category::factory()->create([
        'name' => 'Спорт',
        'slug' => 'sport',
    ]);

    $feed = RssFeed::factory()->create([
        'category_id' => $category->id,
        'title' => 'Спорт',
        'source_name' => '',
        'extra_settings' => null,
    ]);

    $item = simplexml_load_string(<<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<item>
  <title>Матч КХЛ</title>
  <link>https://example.test/khl-match</link>
  <description><![CDATA[<p>Body text.</p>]]></description>
  <category>Спорт: КХЛ</category>
  <pubDate>Mon, 01 Jan 2024 12:00:00 +0000</pubDate>
</item>
XML, 'SimpleXMLElement', LIBXML_NOCDATA);

    $article = invokeRssMethod(new RssParserService, 'processItem', $item, $feed->category_id, $feed->id, $feed);
    $subCategory = SubCategory::query()
        ->where('category_id', $category->id)
        ->where('slug', 'kkhl')
        ->first();

    expect($article)->toBeInstanceOf(Article::class)
        ->and($subCategory)->not->toBeNull()
        ->and($subCategory?->name)->toBe('КХЛ')
        ->and($article?->sub_category_id)->toBe($subCategory?->id)
        ->and($article?->tags->pluck('name')->all())->toContain('Спорт: КХЛ');
});

it('maps aggregate feed item categories to project categories and subcategories', function () {
    $aggregateCategory = Category::factory()->create([
        'name' => 'Главные новости',
        'slug' => 'main',
    ]);
    $societyCategory = Category::factory()->create([
        'name' => 'Общество',
        'slug' => 'society',
    ]);

    $feed = RssFeed::factory()->create([
        'category_id' => $aggregateCategory->id,
        'title' => 'Главные новости',
        'source_name' => '',
        'extra_settings' => null,
    ]);

    $item = simplexml_load_string(<<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<item>
  <title>История дня</title>
  <link>https://example.test/story-of-day</link>
  <description><![CDATA[<p>Body text.</p>]]></description>
  <category>Общество: Жизнь</category>
  <pubDate>Mon, 01 Jan 2024 12:00:00 +0000</pubDate>
</item>
XML, 'SimpleXMLElement', LIBXML_NOCDATA);

    $article = invokeRssMethod(new RssParserService, 'processItem', $item, $feed->category_id, $feed->id, $feed);
    $subCategory = SubCategory::query()
        ->where('category_id', $societyCategory->id)
        ->where('name', 'Жизнь')
        ->first();

    expect($article)->toBeInstanceOf(Article::class)
        ->and($article?->category_id)->toBe($societyCategory->id)
        ->and($subCategory)->not->toBeNull()
        ->and($subCategory?->slug)->toBe('zhizn')
        ->and($article?->sub_category_id)->toBe($subCategory?->id)
        ->and($article?->tags->pluck('name')->all())->toContain('Общество: Жизнь');
});

it('reassigns duplicate articles to resolved rss taxonomy on subsequent parses', function () {
    $aggregateCategory = Category::factory()->create([
        'name' => 'Все новости',
        'slug' => 'all',
    ]);
    $societyCategory = Category::factory()->create([
        'name' => 'Общество',
        'slug' => 'society',
    ]);

    $feed = RssFeed::factory()->create([
        'category_id' => $aggregateCategory->id,
        'title' => 'Все новости',
        'source_name' => '',
        'extra_settings' => null,
    ]);

    $article = Article::factory()->create([
        'category_id' => $aggregateCategory->id,
        'sub_category_id' => null,
        'rss_feed_id' => $feed->id,
        'source_guid' => 'duplicate-story',
        'source_url' => 'https://example.test/duplicate-story',
        'status' => 'published',
        'published_at' => now()->subHour(),
    ]);

    $item = simplexml_load_string(<<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<item>
  <title>Повторный импорт</title>
  <guid>duplicate-story</guid>
  <link>https://example.test/duplicate-story</link>
  <description><![CDATA[<p>Body text.</p>]]></description>
  <category>Общество: Жизнь</category>
  <pubDate>Mon, 01 Jan 2024 12:00:00 +0000</pubDate>
</item>
XML, 'SimpleXMLElement', LIBXML_NOCDATA);

    $processed = invokeRssMethod(new RssParserService, 'processItem', $item, $feed->category_id, $feed->id, $feed);
    $subCategory = SubCategory::query()
        ->where('category_id', $societyCategory->id)
        ->where('name', 'Жизнь')
        ->first();

    $article->refresh();

    expect($processed)->toBeNull()
        ->and($article->category_id)->toBe($societyCategory->id)
        ->and($subCategory)->not->toBeNull()
        ->and($article->sub_category_id)->toBe($subCategory?->id);
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

    $feed = withoutExpandedFactoryRelationships(fn () => RssFeed::factory()->make([
        'source_name' => '',
        'auto_publish' => false,
        'extra_settings' => [
            'status' => 'pending',
            'content_type' => 'analysis',
            'source_name' => 'Custom Source',
            'default_author' => 'Feed Author',
            'short_description_length' => 10,
        ],
    ]));

    $data = invokeRssMethod(new RssParserService, 'buildArticleData', $item, 1, 1, $feed);

    expect($data['status'])->toBe('pending')
        ->and($data['content_type'])->toBe('analysis')
        ->and($data['source_name'])->toBe('Custom Source')
        ->and($data['author'])->toBe('Feed Author')
        ->and($data['short_description'])->toBe('Body text...');
});

it('enriches article data from the source page when a source link is available', function () {
    Http::preventStrayRequests();

    Http::fake([
        'https://example.test/source-article' => Http::response(<<<'HTML'
<!DOCTYPE html>
<html lang="ru">
  <head>
    <title>Source page fallback title | Example</title>
    <meta property="og:title" content="OG page title">
    <meta name="description" content="OG page subtitle">
    <meta property="og:image" content="https://cdn.example.test/og-image.jpg">
    <meta name="author" content="Meta Author">
    <meta property="article:published_time" content="2026-03-08T14:29:54+03:00">
    <link rel="canonical" href="/articles/source-article">
    <script type="application/ld+json">
      {
        "@context": "https://schema.org",
        "@type": "NewsArticle",
        "headline": "Structured page title",
        "description": "Structured page subtitle",
        "author": {
          "@type": "Person",
          "name": "Structured Author",
          "url": "https://example.test/authors/structured-author"
        },
        "publisher": {
          "@type": "Organization",
          "name": "Structured Publisher"
        },
        "datePublished": "2026-03-08T14:29:54+03:00",
        "image": "https://cdn.example.test/structured-image.jpg",
        "articleBody": "Fallback article body from structured data."
      }
    </script>
  </head>
  <body>
    <main>
      <div article-item-type="html"><div><p>First paragraph from source page.</p></div></div>
      <div article-item-type="html"><div><h3>Second source block</h3></div></div>
      <figure><figcaption>Фото: Example Agency</figcaption></figure>
    </main>
  </body>
</html>
HTML, 200),
    ]);

    $item = simplexml_load_string(<<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<item>
  <title>RSS title</title>
  <link>https://example.test/source-article</link>
  <description><![CDATA[<p>RSS short text</p>]]></description>
  <pubDate>Mon, 01 Jan 2024 12:00:00 +0000</pubDate>
</item>
XML, 'SimpleXMLElement', LIBXML_NOCDATA);

    $feed = withoutExpandedFactoryRelationships(fn () => RssFeed::factory()->make([
        'source_name' => '',
        'auto_publish' => true,
    ]));

    $data = invokeRssMethod(new RssParserService, 'buildArticleData', $item, 1, 1, $feed);

    expect($data['title'])->toBe('Structured page title')
        ->and($data['slug'])->toBe('structured-page-title')
        ->and($data['short_description'])->toBe('Structured page subtitle')
        ->and($data['full_description'])->toContain('First paragraph from source page.')
        ->and($data['full_description'])->toContain('Second source block')
        ->and($data['image_url'])->toBe('https://cdn.example.test/structured-image.jpg')
        ->and($data['image_caption'])->toBe('Фото: Example Agency')
        ->and($data['author'])->toBe('Structured Author')
        ->and($data['author_url'])->toBe('https://example.test/authors/structured-author')
        ->and($data['source_name'])->toBe('Structured Publisher')
        ->and($data['meta_title'])->toBe('Structured page title')
        ->and($data['meta_description'])->toBe('Structured page subtitle')
        ->and($data['canonical_url'])->toBe('https://example.test/articles/source-article')
        ->and($data['structured_data'])->toBeArray()
        ->and($data['structured_data']['headline'])->toBe('Structured page title')
        ->and($data['published_at'])->toBeInstanceOf(Carbon::class)
        ->and($data['reading_time'])->toBeGreaterThan(0);
});

it('uses feed source page selector overrides when enriching linked articles', function () {
    Http::preventStrayRequests();

    Http::fake([
        'https://example.test/custom-selectors' => Http::response(<<<'HTML'
<!DOCTYPE html>
<html lang="en">
  <head>
    <title>Ignored title</title>
  </head>
  <body>
    <header>
      <h1 class="hero-title">Custom selector title</h1>
      <p class="hero-subtitle">Custom selector subtitle</p>
    </header>
    <div class="story-byline">
      <a class="story-author" href="/authors/custom-selector">Custom Selector Author</a>
    </div>
    <div class="story-media">
      <img data-src="/images/custom-selector.jpg" alt="Hero image">
    </div>
    <section class="story-body">
      <div class="promo-strip">Promo should disappear.</div>
      <p>First body paragraph from the linked page.</p>
      <p>Second paragraph with a <a href="/related">relative link</a>.</p>
    </section>
  </body>
</html>
HTML, 200),
    ]);

    $item = simplexml_load_string(<<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<item>
  <title>RSS fallback title</title>
  <link>https://example.test/custom-selectors</link>
  <description><![CDATA[<p>RSS fallback short text</p>]]></description>
  <pubDate>Mon, 01 Jan 2024 12:00:00 +0000</pubDate>
</item>
XML, 'SimpleXMLElement', LIBXML_NOCDATA);

    $feed = withoutExpandedFactoryRelationships(fn () => RssFeed::factory()->make([
        'source_name' => '',
        'auto_publish' => true,
        'extra_settings' => [
            'source_page_title_selector' => '.hero-title',
            'source_page_subtitle_selector' => '.hero-subtitle',
            'source_page_article_selector' => '.story-body',
            'source_page_author_selector' => '.story-author',
            'source_page_image_selector' => '.story-media img',
            'source_page_remove_selectors' => '.promo-strip',
        ],
    ]));

    $data = invokeRssMethod(new RssParserService, 'buildArticleData', $item, 1, 1, $feed);

    expect($data['title'])->toBe('Custom selector title')
        ->and($data['short_description'])->toBe('Custom selector subtitle')
        ->and($data['author'])->toBe('Custom Selector Author')
        ->and($data['author_url'])->toBe('https://example.test/authors/custom-selector')
        ->and($data['image_url'])->toBe('https://example.test/images/custom-selector.jpg')
        ->and($data['full_description'])->toContain('First body paragraph from the linked page.')
        ->and($data['full_description'])->toContain('https://example.test/related')
        ->and($data['full_description'])->not->toContain('Promo should disappear.');
});

it('prefers the mail preload article state when the source page exposes rich content blocks', function () {
    Http::preventStrayRequests();

    $preloadedState = json_encode([
        'article' => [
            'title' => 'Mail preload title',
            'description' => '<p>Mail preload subtitle.</p>',
            'href' => 'https://example.test/articles/preloaded',
            'published' => [
                'rfc3339' => '2026-03-08T16:41:00+03:00',
            ],
            'modified' => [
                'rfc3339' => '2026-03-08T16:55:00+03:00',
            ],
            'source' => [
                'title' => '© РИА Новости',
            ],
            'authors' => [
                [
                    'name' => 'Preloaded Author',
                    'href' => 'https://example.test/authors/preloaded-author',
                ],
            ],
            'content' => [
                [
                    'type' => 'picture',
                    'attrs' => [
                        'description' => 'Mail preload title',
                        'images' => [
                            'large' => [
                                'url' => 'https://cdn.example.test/preloaded-image.jpg',
                            ],
                        ],
                    ],
                ],
                [
                    'type' => 'html',
                    'html' => '<p>First rich paragraph from preload.</p>',
                ],
                [
                    'type' => 'html',
                    'html' => '<p>Second rich paragraph from preload.</p>',
                ],
            ],
        ],
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

    Http::fake([
        'https://example.test/preloaded-article' => Http::response(<<<HTML
<!DOCTYPE html>
<html lang="ru">
  <head>
    <title>Mail preload title - Новости Mail</title>
    <meta property="og:title" content="Fallback OG title">
    <meta property="og:description" content="Fallback OG subtitle">
    <meta property="og:image" content="https://cdn.example.test/og-fallback.jpg">
    <meta property="marker:source" content="РИА Новости - Общество">
    <meta name="author" content="Meta Author">
    <link rel="canonical" href="https://example.test/articles/preloaded">
    <script type="application/ld+json">
      {
        "@context": "https://schema.org",
        "@type": "NewsArticle",
        "headline": "Structured fallback title",
        "description": "Structured fallback subtitle",
        "datePublished": "2026-03-08T16:41:00+03:00",
        "author": {
          "@type": "Person",
          "name": "Structured Author"
        },
        "publisher": {
          "@type": "Organization",
          "name": "Structured Publisher"
        },
        "image": "https://cdn.example.test/structured-fallback.jpg",
        "articleBody": "Structured fallback body."
      }
    </script>
  </head>
  <body>
    <script id="preload_article">
      window.__PRELOADED_STATE__ARTICLE = {$preloadedState};
    </script>
  </body>
</html>
HTML, 200),
    ]);

    $item = simplexml_load_string(<<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<item>
  <title>RSS title</title>
  <link>https://example.test/preloaded-article</link>
  <description><![CDATA[<p>RSS short text</p>]]></description>
  <pubDate>Mon, 01 Jan 2024 12:00:00 +0000</pubDate>
</item>
XML, 'SimpleXMLElement', LIBXML_NOCDATA);

    $feed = withoutExpandedFactoryRelationships(fn () => RssFeed::factory()->make([
        'source_name' => '',
        'auto_publish' => true,
    ]));

    $data = invokeRssMethod(new RssParserService, 'buildArticleData', $item, 1, 1, $feed);

    expect($data['title'])->toBe('Mail preload title')
        ->and($data['slug'])->toBe('mail-preload-title')
        ->and($data['short_description'])->toBe('Mail preload subtitle.')
        ->and($data['full_description'])->toContain('Mail preload subtitle.')
        ->and($data['full_description'])->toContain('First rich paragraph from preload.')
        ->and($data['full_description'])->toContain('Second rich paragraph from preload.')
        ->and($data['image_url'])->toBe('https://cdn.example.test/preloaded-image.jpg')
        ->and($data['image_caption'])->toBe('РИА Новости')
        ->and($data['author'])->toBe('Preloaded Author')
        ->and($data['author_url'])->toBe('https://example.test/authors/preloaded-author')
        ->and($data['source_name'])->toBe('РИА Новости')
        ->and($data['source_url'])->toBe('https://example.test/articles/preloaded')
        ->and($data['meta_title'])->toBe('Mail preload title')
        ->and($data['meta_description'])->toBe('Mail preload subtitle.')
        ->and($data['canonical_url'])->toBe('https://example.test/articles/preloaded')
        ->and($data['structured_data'])->toBeArray()
        ->and($data['structured_data']['headline'])->toBe('Structured fallback title')
        ->and($data['published_at'])->toBeInstanceOf(Carbon::class)
        ->and($data['published_at']->toIso8601String())->toBe('2026-03-08T16:41:00+03:00')
        ->and($data['last_edited_at'])->toBeInstanceOf(Carbon::class)
        ->and($data['last_edited_at']->toIso8601String())->toBe('2026-03-08T16:55:00+03:00')
        ->and($data['rss_content'])->toContain('First rich paragraph from preload.')
        ->and($data['reading_time'])->toBeGreaterThan(0);
});

it('enriches an existing stored article from its saved source link', function () {
    Http::preventStrayRequests();

    $preloadedState = json_encode([
        'article' => [
            'title' => 'Mail preload title',
            'description' => '<p>Mail preload subtitle.</p>',
            'href' => 'https://example.test/articles/preloaded-existing',
            'published' => [
                'rfc3339' => '2026-03-08T16:41:00+03:00',
            ],
            'modified' => [
                'rfc3339' => '2026-03-08T16:55:00+03:00',
            ],
            'source' => [
                'title' => '© РИА Новости',
            ],
            'authors' => [
                [
                    'name' => 'Preloaded Author',
                    'href' => 'https://example.test/authors/preloaded-author',
                ],
            ],
            'content' => [
                [
                    'type' => 'picture',
                    'attrs' => [
                        'description' => 'Mail preload title',
                        'images' => [
                            'large' => [
                                'url' => 'https://cdn.example.test/preloaded-image.jpg',
                            ],
                        ],
                    ],
                ],
                [
                    'type' => 'html',
                    'html' => '<p>First rich paragraph from preload.</p>',
                ],
                [
                    'type' => 'html',
                    'html' => '<p>Second rich paragraph from preload.</p>',
                ],
            ],
        ],
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

    Http::fake([
        'https://example.test/preloaded-existing' => Http::response(<<<HTML
<!DOCTYPE html>
<html lang="ru">
  <head>
    <title>Mail preload title - Новости Mail</title>
    <meta property="og:title" content="Fallback OG title">
    <meta property="og:description" content="Fallback OG subtitle">
    <meta property="og:image" content="https://cdn.example.test/og-fallback.jpg">
    <meta property="marker:source" content="РИА Новости - Общество">
    <meta name="author" content="Meta Author">
    <link rel="canonical" href="https://example.test/articles/preloaded-existing">
    <script type="application/ld+json">
      {
        "@context": "https://schema.org",
        "@type": "NewsArticle",
        "headline": "Structured fallback title",
        "description": "Structured fallback subtitle",
        "datePublished": "2026-03-08T16:41:00+03:00",
        "author": {
          "@type": "Person",
          "name": "Structured Author"
        },
        "publisher": {
          "@type": "Organization",
          "name": "Structured Publisher"
        },
        "image": "https://cdn.example.test/structured-fallback.jpg",
        "articleBody": "Structured fallback body."
      }
    </script>
  </head>
  <body>
    <script id="preload_article">
      window.__PRELOADED_STATE__ARTICLE = {$preloadedState};
    </script>
  </body>
</html>
HTML, 200),
    ]);

    $article = Article::factory()->create([
        'title' => 'RSS title',
        'source_url' => 'https://example.test/preloaded-existing',
        'short_description' => 'RSS short text',
        'full_description' => null,
        'image_url' => null,
        'canonical_url' => null,
        'meta_title' => null,
        'meta_description' => null,
        'author' => config('rss.article.default_author'),
        'author_url' => null,
        'source_name' => config('rss.source_name'),
        'structured_data' => null,
        'last_edited_at' => null,
    ]);

    $updated = (new RssParserService)->enrichExistingArticle($article, true);

    $article->refresh();

    expect($updated)->toBeTrue()
        ->and($article->title)->toBe('Mail preload title')
        ->and($article->short_description)->toBe('Mail preload subtitle.')
        ->and($article->full_description)->toContain('Mail preload subtitle.')
        ->and($article->full_description)->toContain('First rich paragraph from preload.')
        ->and($article->full_description)->toContain('Second rich paragraph from preload.')
        ->and($article->image_url)->toBe('https://cdn.example.test/preloaded-image.jpg')
        ->and($article->image_caption)->toBe('РИА Новости')
        ->and($article->author)->toBe('Preloaded Author')
        ->and($article->author_url)->toBe('https://example.test/authors/preloaded-author')
        ->and($article->source_name)->toBe('РИА Новости')
        ->and($article->source_url)->toBe('https://example.test/articles/preloaded-existing')
        ->and($article->meta_title)->toBe('Mail preload title')
        ->and($article->meta_description)->toBe('Mail preload subtitle.')
        ->and($article->canonical_url)->toBe('https://example.test/articles/preloaded-existing')
        ->and($article->structured_data)->toBeArray()
        ->and($article->structured_data['headline'])->toBe('Structured fallback title')
        ->and($article->last_edited_at)->toBeInstanceOf(DateTimeInterface::class)
        ->and($article->last_edited_at?->timestamp)->toBe(Carbon::parse('2026-03-08T16:55:00+03:00')->timestamp)
        ->and($article->rss_content)->toContain('First rich paragraph from preload.')
        ->and($article->reading_time)->toBeGreaterThan(0)
        ->and($article->importance)->toBeGreaterThan(0);
});

it('enriches incomplete stored articles without force when source fields are missing', function () {
    Http::preventStrayRequests();

    Http::fake([
        'https://example.test/missing-fields' => Http::response(<<<'HTML'
<!DOCTYPE html>
<html lang="ru">
  <head>
    <title>Source page title</title>
    <meta property="og:title" content="Source page title">
    <meta property="og:description" content="Source page subtitle">
    <meta property="og:site_name" content="Example Publisher">
    <meta property="og:image" content="https://cdn.example.test/source-page.jpg">
    <link rel="canonical" href="/articles/missing-fields">
  </head>
  <body>
    <main>
      <a rel="author" href="/authors/source-author">Source Author</a>
      <div article-item-type="html"><div><p>First body paragraph from source page.</p></div></div>
      <div article-item-type="html"><div><p>Second body paragraph from source page.</p></div></div>
    </main>
  </body>
</html>
HTML, 200),
    ]);

    $article = Article::factory()->create([
        'title' => 'RSS title',
        'source_url' => 'https://example.test/missing-fields',
        'short_description' => 'RSS short text',
        'full_description' => null,
        'image_url' => null,
        'canonical_url' => null,
        'meta_title' => null,
        'meta_description' => null,
        'author' => config('rss.article.default_author'),
        'author_url' => null,
        'source_name' => null,
        'structured_data' => null,
        'last_edited_at' => null,
    ]);

    $updated = (new RssParserService)->enrichExistingArticle($article);

    $article->refresh();

    expect($updated)->toBeTrue()
        ->and($article->title)->toBe('Source page title')
        ->and($article->short_description)->toBe('Source page subtitle')
        ->and($article->full_description)->toContain('First body paragraph from source page.')
        ->and($article->full_description)->toContain('Second body paragraph from source page.')
        ->and($article->image_url)->toBe('https://cdn.example.test/source-page.jpg')
        ->and($article->author)->toBe('Source Author')
        ->and($article->author_url)->toBe('https://example.test/authors/source-author')
        ->and($article->source_name)->toBe('Example Publisher')
        ->and($article->canonical_url)->toBe('https://example.test/articles/missing-fields')
        ->and($article->structured_data)->toBeArray()
        ->and($article->rss_content)->toContain('First body paragraph from source page.');
});

it('persists stored metadata fallbacks when source page enrichment fails', function () {
    Http::preventStrayRequests();

    Http::fake([
        'https://example.test/source-down' => Http::response('Temporarily unavailable', 503),
    ]);

    $article = Article::factory()->create([
        'title' => 'Stored fallback title',
        'source_url' => 'https://example.test/source-down',
        'short_description' => 'Stored fallback subtitle',
        'full_description' => '<p>Stored fallback body paragraph.</p>',
        'rss_content' => null,
        'meta_title' => null,
        'meta_description' => null,
        'canonical_url' => null,
        'source_name' => null,
        'last_edited_at' => null,
    ]);

    $updated = (new RssParserService)->enrichExistingArticle($article);

    $article->refresh();

    expect($updated)->toBeTrue()
        ->and($article->getRawOriginal('meta_title'))->toBe('Stored fallback title')
        ->and($article->getRawOriginal('meta_description'))->toBe('Stored fallback subtitle')
        ->and($article->canonical_url)->toBe('https://example.test/source-down')
        ->and($article->source_name)->toBe('example.test')
        ->and($article->last_edited_at)->not->toBeNull();
});

it('prefers the original publisher marker over generic portal publisher labels', function () {
    Http::preventStrayRequests();

    $preloadedState = json_encode([
        'article' => [
            'title' => 'Linked source title',
            'description' => '<p>Linked source subtitle.</p>',
            'href' => 'https://example.test/articles/original-publisher',
            'source' => [
                'title' => 'Спортс"',
            ],
            'content' => [
                [
                    'type' => 'html',
                    'html' => '<p>Linked source body paragraph.</p>',
                ],
            ],
        ],
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

    Http::fake([
        'https://example.test/original-publisher' => Http::response(<<<HTML
<!DOCTYPE html>
<html lang="ru">
  <head>
    <title>Linked source title</title>
    <meta property="marker:source" content="Sports.Ru - звезды">
    <meta property="og:site_name" content="Спорт Mail">
    <meta property="og:description" content="Linked source subtitle.">
    <link rel="canonical" href="/articles/original-publisher">
    <script type="application/ld+json">
      {
        "@context": "https://schema.org",
        "@type": "NewsArticle",
        "headline": "Linked source title",
        "publisher": {
          "@type": "Organization",
          "name": "Спорт Mail"
        },
        "description": "Linked source subtitle."
      }
    </script>
  </head>
  <body>
    <script id="preload_article">
      window.__PRELOADED_STATE__ARTICLE = {$preloadedState};
    </script>
  </body>
</html>
HTML, 200),
    ]);

    $item = simplexml_load_string(<<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<item>
  <title>RSS title</title>
  <link>https://example.test/original-publisher</link>
  <description><![CDATA[<p>RSS short text</p>]]></description>
</item>
XML, 'SimpleXMLElement', LIBXML_NOCDATA);

    $feed = withoutExpandedFactoryRelationships(fn () => RssFeed::factory()->make([
        'source_name' => '',
        'auto_publish' => true,
    ]));

    $data = invokeRssMethod(new RssParserService, 'buildArticleData', $item, 1, 1, $feed);

    expect($data['source_name'])->toBe('Sports.Ru')
        ->and($data['title'])->toBe('Linked source title')
        ->and($data['short_description'])->toBe('Linked source subtitle.');
});

it('refreshes duplicate rss items into the existing article instead of inserting a new row', function () {
    Http::preventStrayRequests();

    Http::fake([
        'https://example.test/duplicate-source' => Http::response(<<<'HTML'
<!DOCTYPE html>
<html lang="ru">
  <head>
    <title>Duplicate source title</title>
    <meta property="og:title" content="Duplicate source title">
    <meta property="og:description" content="Duplicate source subtitle">
    <meta property="og:site_name" content="Example Publisher">
    <meta property="og:image" content="https://cdn.example.test/duplicate-source.jpg">
    <link rel="canonical" href="/articles/duplicate-source">
  </head>
  <body>
    <main>
      <a rel="author" href="/authors/duplicate-author">Duplicate Author</a>
      <div article-item-type="html"><div><p>Duplicate body paragraph.</p></div></div>
    </main>
  </body>
</html>
HTML, 200),
    ]);

    $feed = RssFeed::factory()->create();

    $article = Article::factory()->create([
        'category_id' => $feed->category_id,
        'rss_feed_id' => null,
        'title' => 'RSS duplicate title',
        'source_url' => 'https://example.test/duplicate-source',
        'source_guid' => null,
        'short_description' => 'RSS duplicate short text',
        'full_description' => null,
        'image_url' => null,
        'canonical_url' => null,
        'meta_title' => null,
        'meta_description' => null,
        'author' => config('rss.article.default_author'),
        'author_url' => null,
        'source_name' => null,
        'structured_data' => null,
        'published_at' => null,
    ]);

    $item = simplexml_load_string(<<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<item>
  <title>RSS duplicate title</title>
  <link>https://example.test/duplicate-source</link>
  <guid>duplicate-guid</guid>
  <description><![CDATA[<p>RSS duplicate short text</p>]]></description>
  <pubDate>Mon, 01 Jan 2024 12:00:00 +0000</pubDate>
</item>
XML, 'SimpleXMLElement', LIBXML_NOCDATA);

    $processed = invokeRssMethod(new RssParserService, 'processItem', $item, $feed->category_id, $feed->id, $feed);

    $article->refresh();

    expect($processed)->toBeNull()
        ->and(Article::query()->count())->toBe(1)
        ->and($article->rss_feed_id)->toBe($feed->id)
        ->and($article->source_guid)->toBe('duplicate-guid')
        ->and($article->title)->toBe('Duplicate source title')
        ->and($article->short_description)->toBe('Duplicate source subtitle')
        ->and($article->full_description)->toContain('Duplicate body paragraph.')
        ->and($article->image_url)->toBe('https://cdn.example.test/duplicate-source.jpg')
        ->and($article->author)->toBe('Duplicate Author')
        ->and($article->author_url)->toBe('https://example.test/authors/duplicate-author')
        ->and($article->source_name)->toBe('Example Publisher')
        ->and($article->canonical_url)->toBe('https://example.test/articles/duplicate-source')
        ->and($article->structured_data)->toBeArray();
});

it('falls back to known enum values when feed overrides are invalid', function () {
    $item = simplexml_load_string(<<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<item>
  <title>Enum fallback test</title>
  <link>https://example.test/fallback</link>
  <description><![CDATA[<p>Body text for enum fallback</p>]]></description>
</item>
XML, 'SimpleXMLElement', LIBXML_NOCDATA);

    $feed = withoutExpandedFactoryRelationships(fn () => RssFeed::factory()->make([
        'auto_publish' => true,
        'extra_settings' => [
            'status' => 'scheduled',
            'content_type' => 'video',
        ],
    ]));

    $data = invokeRssMethod(new RssParserService, 'buildArticleData', $item, 1, 1, $feed);

    expect($data['status'])->toBe(ArticleStatus::Published->value)
        ->and($data['content_type'])->toBe(ArticleContentType::News->value);
});

it('keeps parsing rss items when the source page enrichment request fails', function () {
    Http::preventStrayRequests();

    Http::fake([
        'https://example.test/feed.xml' => Http::response(<<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<rss>
  <channel>
    <item>
      <title>RSS fallback title</title>
      <link>https://example.test/source-down</link>
      <description><![CDATA[<p>RSS body used as fallback.</p>]]></description>
    </item>
  </channel>
</rss>
XML, 200),
        'https://example.test/source-down' => Http::response('Temporarily unavailable', 503),
    ]);

    $feed = RssFeed::factory()->create([
        'url' => 'https://example.test/feed.xml',
    ]);

    $result = (new RssParserService)->parseFeed($feed, 'manual');
    $article = Article::query()->where('rss_feed_id', $feed->id)->firstOrFail();

    expect($result)->toMatchArray([
        'new' => 1,
        'skip' => 0,
        'errors' => 0,
        'error' => null,
    ])
        ->and($article->title)->toBe('RSS fallback title')
        ->and($article->source_name)->toBeNull()
        ->and($article->short_description)->toBe('RSS body used as fallback.')
        ->and($article->full_description)->toBeNull()
        ->and($article->rss_content)->toContain('RSS body used as fallback.');
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
    $logPath = storage_path('framework/testing/rss-batch.log');

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
        ->and($results[$second->id]['feed'])->toBe($second->title)
        ->and(File::exists($logPath))->toBeTrue()
        ->and(File::get($logPath))->toContain('RSS batch sending.')
        ->and(File::get($logPath))->toContain('RSS batch finished.');

    File::delete($logPath);
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
        ->and($article->status)->toBe(ArticleStatus::Draft)
        ->and($article->rss_feed_id)->toBe($feed->id)
        ->and($article->title)->toBe('Imported article')
        ->and($article->source_url)->toBe('https://example.test/imported-article')
        ->and($article->tags)->toHaveCount(1)
        ->and($article->tags->first()?->name)->toBe('Политика')
        ->and(Article::query()->whereKey($article->id)->exists())->toBeTrue();
});
