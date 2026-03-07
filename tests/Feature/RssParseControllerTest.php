<?php

use App\Models\Article;
use App\Models\Category;
use App\Models\RssFeed;
use App\Models\User;
use App\Services\RssParserService;

afterEach(function () {
    \Mockery::close();
});

it('requires authentication for the rss dashboard', function () {
    $this->get(route('rss.index'))
        ->assertRedirect(route('login'));
});

it('renders the rss dashboard for authenticated users', function () {
    $user = User::factory()->create();
    $category = Category::factory()->create(['name' => 'Politics']);
    RssFeed::factory()->create([
        'category_id' => $category->id,
        'title' => 'Politics Feed',
    ]);
    Article::factory()->create([
        'category_id' => $category->id,
        'status' => 'published',
        'published_at' => now()->subHour(),
    ]);

    $this->actingAs($user)
        ->get(route('rss.index'))
        ->assertSuccessful()
        ->assertSee('RSS Feed Manager')
        ->assertSee('Politics Feed');
});

it('parses a single feed and returns json', function () {
    $user = User::factory()->create();
    $feed = RssFeed::factory()->create();

    $parser = \Mockery::mock(RssParserService::class);
    $parser->shouldReceive('parseFeed')
        ->once()
        ->with(\Mockery::on(fn (RssFeed $model): bool => $model->is($feed)))
        ->andReturn([
            'feed_title' => $feed->title,
            'new' => 3,
            'skipped' => 1,
            'errors' => 0,
            'error_message' => null,
        ]);

    app()->instance(RssParserService::class, $parser);

    $this->actingAs($user)
        ->postJson(route('rss.parse-feed', $feed->id))
        ->assertSuccessful()
        ->assertJsonFragment([
            'success' => true,
            'new' => 3,
            'skipped' => 1,
            'errors' => 0,
        ]);
});

it('parses all feeds and redirects back with a summary', function () {
    $user = User::factory()->create();

    $parser = \Mockery::mock(RssParserService::class);
    $parser->shouldReceive('parseAllFeeds')
        ->once()
        ->andReturn([
            1 => ['feed_title' => 'Feed 1', 'new' => 2, 'skipped' => 1, 'errors' => 0, 'error_message' => null],
            2 => ['feed_title' => 'Feed 2', 'new' => 1, 'skipped' => 0, 'errors' => 0, 'error_message' => null],
        ]);

    app()->instance(RssParserService::class, $parser);

    $this->actingAs($user)
        ->post(route('rss.parse-all'))
        ->assertRedirect(route('rss.index'))
        ->assertSessionHas('status');
});

it('parses a category and aggregates results', function () {
    $user = User::factory()->create();
    $category = Category::factory()->create(['slug' => 'sport']);
    $first = RssFeed::factory()->create(['category_id' => $category->id]);
    $second = RssFeed::factory()->create(['category_id' => $category->id]);

    $parser = \Mockery::mock(RssParserService::class);
    $parser->shouldReceive('parseFeed')
        ->once()
        ->with(\Mockery::on(fn (RssFeed $model): bool => $model->is($first)))
        ->andReturn([
            'feed_title' => $first->title,
            'new' => 1,
            'skipped' => 0,
            'errors' => 0,
            'error_message' => null,
        ]);
    $parser->shouldReceive('parseFeed')
        ->once()
        ->with(\Mockery::on(fn (RssFeed $model): bool => $model->is($second)))
        ->andReturn([
            'feed_title' => $second->title,
            'new' => 0,
            'skipped' => 2,
            'errors' => 0,
            'error_message' => null,
        ]);

    app()->instance(RssParserService::class, $parser);

    $this->actingAs($user)
        ->postJson(route('rss.parse-category', $category->slug))
        ->assertSuccessful()
        ->assertJsonFragment([
            'success' => true,
            'new' => 1,
            'skipped' => 2,
            'errors' => 0,
        ]);
});
