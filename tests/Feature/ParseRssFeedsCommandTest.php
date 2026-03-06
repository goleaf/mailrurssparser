<?php

use App\Models\RssFeed;
use App\Services\RssParserService;
use Symfony\Component\Console\Command\Command as SymfonyCommand;

afterEach(function () {
    \Mockery::close();
});

it('returns failure when no feeds match', function () {
    $this->artisan('rss:parse')
        ->expectsOutputToContain('No matching active feeds found.')
        ->assertExitCode(SymfonyCommand::FAILURE);
});

it('lists feeds in dry run without parsing', function () {
    RssFeed::factory()->create(['is_active' => true]);

    $parser = \Mockery::mock(RssParserService::class);
    $parser->shouldNotReceive('parseFeed');
    app()->instance(RssParserService::class, $parser);

    $this->artisan('rss:parse --dry-run')
        ->assertExitCode(SymfonyCommand::SUCCESS);
});

it('returns failure when any feed has an error', function () {
    $feed = RssFeed::factory()->create(['is_active' => true]);

    $parser = \Mockery::mock(RssParserService::class);
    $parser->shouldReceive('parseFeed')
        ->once()
        ->with(\Mockery::type(RssFeed::class))
        ->andReturn([
            'feed_title' => $feed->title,
            'new' => 0,
            'skipped' => 0,
            'errors' => 1,
            'error_message' => 'boom',
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
        ->with(\Mockery::type(RssFeed::class))
        ->andReturn([
            'feed_title' => $feed->title,
            'new' => 1,
            'skipped' => 0,
            'errors' => 0,
            'error_message' => null,
        ]);
    app()->instance(RssParserService::class, $parser);

    $this->artisan('rss:parse')
        ->assertExitCode(SymfonyCommand::SUCCESS);
});
