<?php

use App\Filament\Resources\RssFeeds\Pages\CreateRssFeed;
use App\Filament\Resources\RssFeeds\Pages\ListRssFeeds;
use App\Models\RssFeed;
use App\Models\RssParseLog;
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
        ->with(\Mockery::type(RssFeed::class), 'filament')
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
        ->with('filament')
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

it('creates an rss feed with the extended feed settings form', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test(CreateRssFeed::class)
        ->fillForm([
            'category_id' => \App\Models\Category::factory()->create()->id,
            'title' => 'Главные новости',
            'url' => 'https://news.mail.ru/rss/main/',
            'source_name' => 'Новости Mail',
            'is_active' => true,
            'auto_publish' => true,
            'auto_featured' => false,
            'fetch_interval' => 15,
        ])
        ->call('create')
        ->assertHasNoFormErrors()
        ->assertNotified()
        ->assertRedirect();

    $feed = RssFeed::query()->where('url', 'https://news.mail.ru/rss/main/')->first();

    expect($feed)
        ->not()->toBeNull()
        ->and($feed?->source_name)->toBe('Новости Mail')
        ->and($feed?->fetch_interval)->toBe(15)
        ->and($feed?->auto_publish)->toBeTrue();
});

it('renders the recent parse logs footer below the rss feeds table', function () {
    $this->actingAs(User::factory()->create());

    $feed = RssFeed::factory()->create([
        'title' => 'Politics feed',
    ]);

    RssParseLog::factory()->create([
        'rss_feed_id' => $feed->id,
        'new_count' => 4,
        'duration_ms' => 850,
        'success' => true,
    ]);

    Livewire::test(ListRssFeeds::class)
        ->assertSee('Recent Parse Logs')
        ->assertSee('Politics feed')
        ->assertSee('850 ms');
});
