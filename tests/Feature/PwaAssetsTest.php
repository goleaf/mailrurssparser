<?php

use App\Models\Article;
use App\Models\Category;

it('renders the app shell with pwa meta tags', function () {
    $this->get('/')
        ->assertOk()
        ->assertSee('name="app-name"', false)
        ->assertSee('name="app-url"', false)
        ->assertSee('name="robots" content="index, follow"', false)
        ->assertSee('rel="manifest" href="/manifest.json"', false)
        ->assertSee('name="theme-color" content="#1D4ED8"', false)
        ->assertSee(
            'name="apple-mobile-web-app-capable" content="yes"',
            false,
        )
        ->assertSee(
            'name="apple-mobile-web-app-status-bar-style" content="default"',
            false,
        )
        ->assertSee('rel="preconnect" href="https://fonts.googleapis.com"', false)
        ->assertSee('rel="preconnect" href="'.config('rss.feed_origin').'"', false)
        ->assertSee('type="application/ld+json"', false);
});

it('renders article pages with seo relation overrides', function () {
    $category = Category::factory()->create(['slug' => 'politics']);
    $article = Article::factory()->create([
        'category_id' => $category->id,
        'slug' => 'editor-controlled-seo',
        'title' => 'Imported title',
        'status' => 'published',
        'published_at' => now()->subHour(),
    ]);

    $article->seo()->update([
        'title' => 'Editor controlled SEO title',
        'description' => 'Editor controlled SEO description',
        'image' => 'https://cdn.example.test/articles/editor-controlled.jpg',
        'robots' => 'noindex, follow',
        'canonical_url' => 'https://news.example.test/articles/editor-controlled-seo',
    ]);

    $this->get(route('articles.show', ['slug' => $article->slug]))
        ->assertOk()
        ->assertSee('<title>Editor controlled SEO title</title>', false)
        ->assertSee('name="robots" content="noindex, follow"', false)
        ->assertSee('property="og:image" content="https://cdn.example.test/articles/editor-controlled.jpg"', false)
        ->assertSee('name="twitter:title" content="Editor controlled SEO title"', false)
        ->assertSee('rel="canonical" href="https://news.example.test/articles/editor-controlled-seo"', false);
});

it('renders the offline fallback page', function () {
    $this->get('/offline.html')
        ->assertOk()
        ->assertSee('Нет подключения к интернету');
});

it('renders the robots.txt response', function () {
    $response = $this->get('/robots.txt');

    $response->assertOk()
        ->assertHeader('Content-Type', 'text/plain; charset=UTF-8')
        ->assertSee('User-agent: *')
        ->assertSee('Sitemap: '.url('sitemap.xml'));
});

it('ships the manifest, service worker, and icons', function () {
    expect(public_path('manifest.json'))->toBeFile()
        ->and(public_path('sw.js'))->toBeFile()
        ->and(public_path('icons/icon-192.png'))->toBeFile()
        ->and(public_path('icons/icon-512.png'))->toBeFile();

    $manifest = json_decode(
        file_get_contents(public_path('manifest.json')),
        true,
        flags: JSON_THROW_ON_ERROR,
    );

    expect($manifest)->toMatchArray([
        'name' => 'Новостной портал',
        'short_name' => 'Новости',
        'theme_color' => '#1D4ED8',
        'lang' => 'ru',
    ]);
});
