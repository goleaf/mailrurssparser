<?php

use App\Filament\Pages\ManageRssFeeds;
use App\Models\RssFeed;
use App\Models\User;
use App\Services\RssParserService;
use Livewire\Livewire;

afterEach(function () {
    \Mockery::close();
});

it('loads feeds on the custom page', function () {
    $this->actingAs(User::factory()->create());

    $feed = RssFeed::factory()->create(['title' => 'Main feed']);

    Livewire::test(ManageRssFeeds::class)
        ->assertSee($feed->title);
});

it('parses all feeds from the custom page', function () {
    $this->actingAs(User::factory()->create());

    $parser = \Mockery::mock(RssParserService::class);
    $parser->shouldReceive('parseAllFeeds')
        ->once()
        ->with('filament')
        ->andReturn([
            1 => [
                'feed' => 'Feed 1',
                'new' => 2,
                'skip' => 1,
                'errors' => 0,
                'error' => null,
            ],
        ]);

    app()->instance(RssParserService::class, $parser);

    Livewire::test(ManageRssFeeds::class)
        ->call('parseAll')
        ->assertSet('isParsing', false)
        ->assertSet('results.1.new', 2);
});

it('parses a single feed from the custom page', function () {
    $this->actingAs(User::factory()->create());

    $feed = RssFeed::factory()->create();

    $parser = \Mockery::mock(RssParserService::class);
    $parser->shouldReceive('parseFeed')
        ->once()
        ->withArgs(fn (RssFeed $rssFeed, string $triggeredBy): bool => $rssFeed->is($feed) && $triggeredBy === 'filament')
        ->andReturn([
            'feed' => $feed->title,
            'new' => 1,
            'skip' => 0,
            'errors' => 0,
            'error' => null,
        ]);

    app()->instance(RssParserService::class, $parser);

    Livewire::test(ManageRssFeeds::class)
        ->call('parseFeed', $feed->id)
        ->assertSet("results.{$feed->id}.new", 1)
        ->assertSet('parsingFeedId', null);
});

it('toggles a feed active state from the custom page', function () {
    $this->actingAs(User::factory()->create());

    $feed = RssFeed::factory()->create(['is_active' => true]);

    Livewire::test(ManageRssFeeds::class)
        ->call('toggleFeed', $feed->id)
        ->assertSet('feeds.0.is_active', false);
});
