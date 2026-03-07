<?php

use App\Filament\Resources\RssFeeds\Pages\ListRssFeeds;
use App\Models\RssFeed;
use App\Models\User;
use App\Services\RssParserService;
use Filament\Actions\Testing\TestAction;
use Livewire\Livewire;

afterEach(function () {
    \Mockery::close();
});

it('parses a feed from the table action', function () {
    $this->actingAs(User::factory()->create());

    $feed = RssFeed::factory()->create();

    $parser = \Mockery::mock(RssParserService::class);
    $parser->shouldReceive('parseFeed')
        ->once()
        ->with(\Mockery::type(RssFeed::class))
        ->andReturn([
            'feed' => $feed->title,
            'new' => 2,
            'skip' => 1,
            'errors' => 0,
            'error' => null,
        ]);

    app()->instance(RssParserService::class, $parser);

    Livewire::test(ListRssFeeds::class)
        ->callAction(TestAction::make('parseNow')->table($feed));
});

it('parses all feeds from the header action', function () {
    $this->actingAs(User::factory()->create());

    RssFeed::factory()->count(2)->create();

    $parser = \Mockery::mock(RssParserService::class);
    $parser->shouldReceive('parseAllFeeds')
        ->once()
        ->andReturn([
            1 => [
                'feed' => 'Feed 1',
                'new' => 1,
                'skip' => 0,
                'errors' => 0,
                'error' => null,
            ],
            2 => [
                'feed' => 'Feed 2',
                'new' => 0,
                'skip' => 2,
                'errors' => 0,
                'error' => null,
            ],
        ]);

    app()->instance(RssParserService::class, $parser);

    Livewire::test(ListRssFeeds::class)
        ->callAction('parseAllFeeds');
});
