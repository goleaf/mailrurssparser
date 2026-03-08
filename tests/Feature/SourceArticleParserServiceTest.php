<?php

use App\Services\SourceArticleParserService;
use Carbon\Carbon;

it('parses rich article metadata from source page html', function () {
    $html = <<<'HTML'
<!DOCTYPE html>
<html lang="en">
  <head>
    <title>Fallback title | Example</title>
    <meta property="og:title" content="Open Graph title">
    <meta property="og:description" content="Open Graph description">
    <meta property="og:image" content="/images/og.jpg">
    <meta property="og:site_name" content="Example News">
    <meta name="author" content="Fallback Author">
    <meta property="article:published_time" content="2026-03-08T14:29:54+03:00">
    <meta property="article:modified_time" content="2026-03-08T15:45:00+03:00">
    <meta property="article:section" content="Politics">
    <meta name="keywords" content="world, politics, briefing">
    <link rel="canonical" href="/news/rich-article">
    <script type="application/ld+json">
      {
        "@context": "https://schema.org",
        "@type": "NewsArticle",
        "headline": "Structured title",
        "alternativeHeadline": "Structured subtitle",
        "description": "Structured description",
        "author": {
          "@type": "Person",
          "name": "Structured Author",
          "url": "/authors/structured-author"
        },
        "publisher": {
          "@type": "Organization",
          "name": "Structured Publisher"
        },
        "datePublished": "2026-03-08T14:29:54+03:00",
        "dateModified": "2026-03-08T15:45:00+03:00",
        "image": {
          "@type": "ImageObject",
          "url": "/images/structured.jpg"
        }
      }
    </script>
  </head>
  <body>
    <article class="article__body">
      <p>First paragraph from the linked article.</p>
      <p>Second paragraph with <a href="/story">relative story link</a>.</p>
      <figure>
        <img src="/images/inline.jpg" alt="Inline image">
        <figcaption>Image caption from the page</figcaption>
      </figure>
    </article>
  </body>
</html>
HTML;

    $parsed = app(SourceArticleParserService::class)->parseHtml($html, 'https://example.test/raw-link', [
        'source_page_min_body_characters' => 40,
    ]);

    expect($parsed['title'])->toBe('Structured title')
        ->and($parsed['subtitle'])->toBe('Structured subtitle')
        ->and($parsed['short_description'])->toBe('Structured subtitle')
        ->and($parsed['full_description'])->toContain('First paragraph from the linked article.')
        ->and($parsed['full_description'])->toContain('https://example.test/story')
        ->and($parsed['image_url'])->toBe('https://example.test/images/structured.jpg')
        ->and($parsed['image_caption'])->toBe('Image caption from the page')
        ->and($parsed['author'])->toBe('Structured Author')
        ->and($parsed['author_url'])->toBe('https://example.test/authors/structured-author')
        ->and($parsed['source_name'])->toBe('Structured Publisher')
        ->and($parsed['canonical_url'])->toBe('https://example.test/news/rich-article')
        ->and($parsed['published_at'])->toBeInstanceOf(Carbon::class)
        ->and($parsed['published_at']->toIso8601String())->toBe('2026-03-08T14:29:54+03:00')
        ->and($parsed['structured_data'])->toBeArray()
        ->and($parsed['structured_data']['@type'])->toBe('NewsArticle')
        ->and($parsed['structured_data']['headline'])->toBe('Structured title')
        ->and($parsed['structured_data']['alternativeHeadline'])->toBe('Structured subtitle')
        ->and($parsed['structured_data']['image']['url'])->toBe('https://example.test/images/structured.jpg')
        ->and($parsed['structured_data']['author']['url'])->toBe('https://example.test/authors/structured-author')
        ->and($parsed['structured_data']['keywords'])->toBe(['world', 'politics', 'briefing']);
});

it('honors feed level selectors and cleanup rules when parsing source pages', function () {
    $html = <<<'HTML'
<!DOCTYPE html>
<html lang="en">
  <head>
    <title>Ignored fallback title</title>
  </head>
  <body>
    <div class="hero">
      <h1 class="hero-title">Override title</h1>
      <p class="hero-subtitle">Override subtitle</p>
    </div>
    <div class="story-meta">
      <a class="story-author" href="/authors/override-author">Override Author</a>
    </div>
    <div class="story-media">
      <img data-src="/images/override.jpg" alt="Override image">
    </div>
    <section class="story-body">
      <div class="sponsored">Remove me entirely</div>
      <p>Story body kept after cleanup.</p>
      <p>Another paragraph with a <a href="/more">relative link</a>.</p>
    </section>
  </body>
</html>
HTML;

    $parsed = app(SourceArticleParserService::class)->parseHtml($html, 'https://example.test/news/raw', [
        'source_page_title_selector' => '.hero-title',
        'source_page_subtitle_selector' => '.hero-subtitle',
        'source_page_article_selector' => '.story-body',
        'source_page_author_selector' => '.story-author',
        'source_page_image_selector' => '.story-media img',
        'source_page_remove_selectors' => '.sponsored',
        'source_page_min_body_characters' => 40,
    ]);

    expect($parsed['title'])->toBe('Override title')
        ->and($parsed['subtitle'])->toBe('Override subtitle')
        ->and($parsed['short_description'])->toBe('Override subtitle')
        ->and($parsed['author'])->toBe('Override Author')
        ->and($parsed['author_url'])->toBe('https://example.test/authors/override-author')
        ->and($parsed['image_url'])->toBe('https://example.test/images/override.jpg')
        ->and($parsed['full_description'])->toContain('Story body kept after cleanup.')
        ->and($parsed['full_description'])->toContain('https://example.test/more')
        ->and($parsed['full_description'])->not->toContain('Remove me entirely');
});

it('extracts rich article data from preloaded page state when preload_article is absent', function () {
    $pageState = json_encode([
        'article' => [
            'title' => 'Page state title',
            'description' => '<p>Page state subtitle.</p>',
            'href' => 'https://example.test/page-state-article',
            'published' => [
                'rfc3339' => '2026-03-08T18:02:35+03:00',
            ],
            'modified' => [
                'rfc3339' => '2026-03-08T18:05:00+03:00',
            ],
            'source' => [
                'title' => 'РИА Новости',
            ],
            'authors' => [
                [
                    'name' => 'Ольга Шумейко',
                ],
            ],
            'content' => [
                [
                    'type' => 'picture',
                    'attrs' => [
                        'description' => '',
                    ],
                    'data' => [
                        'large' => [
                            'url' => 'https://cdn.example.test/page-state-image.jpg',
                        ],
                    ],
                ],
                [
                    'type' => 'html',
                    'html' => '<p>First paragraph from page state.</p>',
                ],
                [
                    'type' => 'html',
                    'html' => '<p>Second paragraph from page state.</p>',
                ],
            ],
        ],
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

    $html = <<<HTML
<!DOCTYPE html>
<html lang="ru">
  <head>
    <title>Ignored fallback</title>
    <meta property="og:title" content="Fallback title">
    <meta property="og:description" content="Fallback subtitle">
    <meta property="og:image" content="https://cdn.example.test/fallback-image.jpg">
    <meta property="og:site_name" content="Погода Mail">
    <meta property="marker:source" content="РИА Новости - Погода">
    <link rel="canonical" href="https://example.test/page-state-article">
  </head>
  <body>
    <script>
      window.__PRELOADED_STATE__PAGE = {$pageState};
    </script>
  </body>
</html>
HTML;

    $parsed = app(SourceArticleParserService::class)->parseHtml($html, 'https://example.test/page-state-article');

    expect($parsed['title'])->toBe('Page state title')
        ->and($parsed['subtitle'])->toBe('Page state subtitle.')
        ->and($parsed['short_description'])->toBe('Page state subtitle.')
        ->and($parsed['full_description'])->toContain('First paragraph from page state.')
        ->and($parsed['full_description'])->toContain('Second paragraph from page state.')
        ->and($parsed['image_url'])->toBe('https://cdn.example.test/page-state-image.jpg')
        ->and($parsed['image_caption'])->toBe('РИА Новости')
        ->and($parsed['author'])->toBe('Ольга Шумейко')
        ->and($parsed['source_name'])->toBe('РИА Новости')
        ->and($parsed['canonical_url'])->toBe('https://example.test/page-state-article')
        ->and($parsed['published_at'])->toBeInstanceOf(Carbon::class)
        ->and($parsed['published_at']->toIso8601String())->toBe('2026-03-08T18:02:35+03:00');
});
