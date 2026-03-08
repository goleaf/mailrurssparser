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
        ->assertSee('Менеджер RSS-лент')
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
            'feed' => $feed->title,
            'new' => 3,
            'skip' => 1,
            'errors' => 0,
            'error' => null,
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
            1 => ['feed' => 'Feed 1', 'new' => 2, 'skip' => 1, 'errors' => 0, 'error' => null],
            2 => ['feed' => 'Feed 2', 'new' => 1, 'skip' => 0, 'errors' => 0, 'error' => null],
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
    $parser->shouldReceive('parseFeeds')
        ->once()
        ->with(\Mockery::on(function ($feeds) use ($first, $second): bool {
            return $feeds instanceof \Illuminate\Support\Collection
                && $feeds->count() === 2
                && $feeds->contains(fn (RssFeed $feed): bool => $feed->is($first))
                && $feeds->contains(fn (RssFeed $feed): bool => $feed->is($second));
        }), 'scheduler')
        ->andReturn([
            $first->id => [
                'feed' => $first->title,
                'new' => 1,
                'skip' => 0,
                'errors' => 0,
                'error' => null,
            ],
            $second->id => [
                'feed' => $second->title,
                'new' => 0,
                'skip' => 2,
                'errors' => 0,
                'error' => null,
            ],
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

it('returns failed dependency when a category has no active feeds', function () {
    $user = User::factory()->create();
    $category = Category::factory()->create(['slug' => 'inactive']);
    RssFeed::factory()->create([
        'category_id' => $category->id,
        'is_active' => false,
    ]);

    $this->actingAs($user)
        ->postJson(route('rss.parse-category', $category->slug))
        ->assertFailedDependency()
        ->assertJson([
            'success' => false,
            'message' => 'Для этой категории не найдено активных лент.',
            'new' => 0,
            'skipped' => 0,
            'errors' => 0,
        ]);
});
