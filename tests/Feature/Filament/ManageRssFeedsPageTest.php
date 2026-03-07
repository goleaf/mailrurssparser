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
        ->andReturn([
            1 => [
                'feed_title' => 'Feed 1',
                'new' => 2,
                'skipped' => 1,
                'errors' => 0,
                'error_message' => null,
            ],
        ]);

    app()->instance(RssParserService::class, $parser);

    Livewire::test(ManageRssFeeds::class)
        ->call('parseAll')
        ->assertSet('isParsing', false)
        ->assertSet('parseResults.1.new', 2);
});

it('parses a single feed from the custom page', function () {
    $this->actingAs(User::factory()->create());

    $feed = RssFeed::factory()->create();

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

    Livewire::test(ManageRssFeeds::class)
        ->call('parseSingleFeed', $feed->id)
        ->assertSet("parseResults.{$feed->id}.new", 1)
        ->assertSet('selectedFeedId', null);
});
