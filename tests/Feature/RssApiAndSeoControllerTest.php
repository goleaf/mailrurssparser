<?php

use App\Models\Article;
use App\Models\Category;
use App\Models\RssFeed;
use App\Services\RssParserService;

afterEach(function () {
    \Mockery::close();
});

it('returns rss feed status labels', function () {
    $category = Category::factory()->create(['name' => 'Politics']);

    $disabled = RssFeed::factory()->create([
        'category_id' => $category->id,
        'title' => 'Disabled Feed',
        'is_active' => false,
        'last_error' => null,
        'next_parse_at' => now()->addHour(),
    ]);

    $errored = RssFeed::factory()->create([
        'category_id' => $category->id,
        'title' => 'Error Feed',
        'is_active' => true,
        'last_error' => 'Boom',
        'next_parse_at' => now()->addHour(),
    ]);

    $due = RssFeed::factory()->create([
        'category_id' => $category->id,
        'title' => 'Due Feed',
        'is_active' => true,
        'last_error' => null,
        'next_parse_at' => now()->subMinute(),
    ]);

    $ok = RssFeed::factory()->create([
        'category_id' => $category->id,
        'title' => 'Ok Feed',
        'is_active' => true,
        'last_error' => null,
        'next_parse_at' => now()->addHour(),
    ]);

    $response = $this->getJson(route('api.v1.rss.status'));

    $response->assertSuccessful()
        ->assertJsonFragment([
            'id' => $disabled->id,
            'title' => 'Disabled Feed',
            'category_name' => 'Politics',
            'status_label' => 'Disabled',
        ])
        ->assertJsonFragment([
            'id' => $errored->id,
            'title' => 'Error Feed',
            'status_label' => 'Error',
        ])
        ->assertJsonFragment([
            'id' => $due->id,
            'title' => 'Due Feed',
            'status_label' => 'Due',
        ])
        ->assertJsonFragment([
            'id' => $ok->id,
            'title' => 'Ok Feed',
            'status_label' => 'OK',
        ]);

    expect($response->json('data.0'))->not->toHaveKeys(['category', 'extra_settings']);
});

it('parses a single rss feed through the api endpoint', function () {
    $feed = RssFeed::factory()->create();

    $parser = \Mockery::mock(RssParserService::class);
    $parser->shouldReceive('parseFeed')
        ->once()
        ->with(\Mockery::on(fn (RssFeed $model): bool => $model->is($feed)), 'api')
        ->andReturn([
            'feed' => $feed->title,
            'new' => 2,
            'skip' => 1,
            'errors' => 0,
            'total' => 3,
            'duration_ms' => 25,
            'error' => null,
        ]);

    app()->instance(RssParserService::class, $parser);

    $this->postJson(route('api.v1.rss.parse-feed', $feed->id))
        ->assertSuccessful()
        ->assertJsonFragment([
            'success' => true,
            'feed' => $feed->title,
            'new' => 2,
            'skip' => 1,
            'errors' => 0,
        ]);
});

it('parses all rss feeds through the api endpoint', function () {
    $parser = \Mockery::mock(RssParserService::class);
    $parser->shouldReceive('parseAllFeeds')
        ->once()
        ->with('api')
        ->andReturn([
            1 => ['feed' => 'Feed 1', 'new' => 2, 'skip' => 1, 'errors' => 0, 'error' => null],
            2 => ['feed' => 'Feed 2', 'new' => 1, 'skip' => 0, 'errors' => 0, 'error' => null],
        ]);

    app()->instance(RssParserService::class, $parser);

    $this->postJson(route('api.v1.rss.parse-all'))
        ->assertSuccessful()
        ->assertJsonFragment([
            'success' => true,
        ])
        ->assertJsonPath('totals.new', 3)
        ->assertJsonPath('totals.skip', 1)
        ->assertJsonPath('totals.errors', 0);
});

it('parses an rss category through the api endpoint', function () {
    $category = Category::factory()->create(['slug' => 'sport']);
    $first = RssFeed::factory()->create(['category_id' => $category->id, 'is_active' => true]);
    $second = RssFeed::factory()->create(['category_id' => $category->id, 'is_active' => true]);

    $parser = \Mockery::mock(RssParserService::class);
    $parser->shouldReceive('parseFeed')
        ->once()
        ->with(\Mockery::on(fn (RssFeed $model): bool => $model->is($first)), 'api')
        ->andReturn([
            'feed' => $first->title,
            'new' => 1,
            'skip' => 0,
            'errors' => 0,
            'error' => null,
        ]);
    $parser->shouldReceive('parseFeed')
        ->once()
        ->with(\Mockery::on(fn (RssFeed $model): bool => $model->is($second)), 'api')
        ->andReturn([
            'feed' => $second->title,
            'new' => 0,
            'skip' => 2,
            'errors' => 0,
            'error' => null,
        ]);

    app()->instance(RssParserService::class, $parser);

    $this->postJson(route('api.v1.rss.parse-category', $category->slug))
        ->assertSuccessful()
        ->assertJsonPath('totals.new', 1)
        ->assertJsonPath('totals.skip', 2)
        ->assertJsonPath('totals.errors', 0);
});

it('returns failed dependency when an rss category has no active feeds through the api endpoint', function () {
    $category = Category::factory()->create(['slug' => 'empty-category']);
    RssFeed::factory()->create([
        'category_id' => $category->id,
        'is_active' => false,
    ]);

    $this->postJson(route('api.v1.rss.parse-category', $category->slug))
        ->assertFailedDependency()
        ->assertJson([
            'success' => false,
            'message' => 'No active feeds found for this category.',
            'totals' => [
                'new' => 0,
                'skip' => 0,
                'errors' => 0,
            ],
        ]);
});

it('returns a sitemap xml response', function () {
    $category = Category::factory()->create(['slug' => 'politics']);
    $article = Article::factory()->create([
        'category_id' => $category->id,
        'slug' => 'breaking-news',
        'status' => 'published',
        'published_at' => now()->subHour(),
        'updated_at' => now(),
    ]);

    $response = $this->get(route('sitemap'));

    $response->assertSuccessful()
        ->assertHeaderContains('Content-Type', 'application/xml')
        ->assertHeaderContains('Cache-Control', 'public')
        ->assertHeaderContains('Cache-Control', 'max-age=3600');

    expect($response->getContent())->toContain('<?xml version="1.0" encoding="UTF-8"?>')
        ->and($response->getContent())->toContain('/#/category/'.$category->slug)
        ->and($response->getContent())->toContain('/#/articles/'.$article->slug);
});

it('returns a portal rss xml response', function () {
    $category = Category::factory()->create(['name' => 'Политика']);
    $article = Article::factory()->create([
        'category_id' => $category->id,
        'title' => 'Новая статья',
        'slug' => 'novaya-statya',
        'short_description' => 'Краткое описание',
        'status' => 'published',
        'published_at' => now()->subHour(),
    ]);

    $response = $this->get(route('rss-feed'));

    $response->assertSuccessful()
        ->assertHeaderContains('Content-Type', 'application/rss+xml');

    expect($response->getContent())->toContain('<rss version="2.0">')
        ->and($response->getContent())->toContain('<title>Новая статья</title>')
        ->and($response->getContent())->toContain('/#/articles/'.$article->slug);
});
