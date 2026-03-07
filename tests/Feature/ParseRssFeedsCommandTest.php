<?php

use App\Models\Article;
use App\Models\RssFeed;
use App\Services\RssParserService;
use Symfony\Component\Console\Command\Command as SymfonyCommand;

afterEach(function () {
    \Mockery::close();
});

it('returns failure when no feeds match', function () {
    $this->artisan('rss:parse')
        ->expectsOutputToContain('No matching feeds found.')
        ->assertExitCode(SymfonyCommand::FAILURE);
});

it('uses inspect feed summaries during dry run', function () {
    $feed = RssFeed::factory()->create(['is_active' => true]);

    $parser = \Mockery::mock(RssParserService::class);
    $parser->shouldReceive('inspectFeed')
        ->once()
        ->with(\Mockery::on(fn (RssFeed $model): bool => $model->is($feed)))
        ->andReturn([
            'feed' => $feed->title,
            'items' => 3,
            'new' => 2,
            'skip' => 1,
        ]);
    $parser->shouldNotReceive('parseFeed');
    app()->instance(RssParserService::class, $parser);

    $this->artisan('rss:parse --dry-run')
        ->expectsTable(['Feed', 'Items Found', 'Would Save', 'Would Skip'], [
            [$feed->title, 3, 2, 1],
        ])
        ->assertSuccessful();
});

it('returns failure when any feed parse result contains an error', function () {
    $feed = RssFeed::factory()->create(['is_active' => true]);

    $parser = \Mockery::mock(RssParserService::class);
    $parser->shouldReceive('parseFeed')
        ->once()
        ->with(\Mockery::type(RssFeed::class), 'manual')
        ->andReturn([
            'feed' => $feed->title,
            'new' => 0,
            'skip' => 0,
            'errors' => 1,
            'duration_ms' => 10,
            'error' => 'boom',
        ]);
    app()->instance(RssParserService::class, $parser);

    $this->artisan('rss:parse')
        ->assertExitCode(SymfonyCommand::FAILURE);
});

it('returns success when all feeds parse without errors', function () {
    $feed = RssFeed::factory()->create(['is_active' => true]);

    $parser = \Mockery::mock(RssParserService::class);
    $parser->shouldReceive('parseFeed')
        ->once()
        ->with(\Mockery::type(RssFeed::class), 'manual')
        ->andReturn([
            'feed' => $feed->title,
            'new' => 1,
            'skip' => 0,
            'errors' => 0,
            'duration_ms' => 8,
            'error' => null,
        ]);
    app()->instance(RssParserService::class, $parser);

    $this->artisan('rss:parse')
        ->assertSuccessful();
});

it('cleans old articles in dry run mode', function () {
    $article = Article::factory()->create([
        'status' => 'archived',
        'published_at' => now()->subDays(120),
    ]);
    $article->delete();

    $this->artisan('rss:clean --dry-run --days=90')
        ->expectsOutputToContain('Found 1 articles to clean')
        ->assertSuccessful();
});
