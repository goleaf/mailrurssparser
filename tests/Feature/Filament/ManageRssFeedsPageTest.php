<?php

use App\Filament\Pages\ManageRssFeeds;
use App\Models\Category;
use App\Models\RssFeed;
use App\Services\RssParserService;
use Livewire\Livewire;

afterEach(function () {
    \Mockery::close();
});

it('loads feeds on the custom page', function () {
    $this->actingAs(filamentAdminUser());

    $feed = RssFeed::factory()->create(['title' => 'Main feed']);

    Livewire::test(ManageRssFeeds::class)
        ->assertSee($feed->title);
});

it('renders the redesigned rss manager page over http', function () {
    $this->actingAs(filamentAdminUser());

    $feed = RssFeed::factory()->create(['title' => 'Operations feed']);

    $this->get(route('filament.admin.pages.manage-rss-feeds'))
        ->assertSuccessful()
        ->assertSeeText('RSS Ops')
        ->assertSeeText('Операционный пульт RSS')
        ->assertSeeText('Запустить весь контур')
        ->assertSeeText('Приоритетная очередь')
        ->assertSeeText('Живой контур запусков')
        ->assertSeeText('Каталог лент')
        ->assertSeeText($feed->title)
        ->assertSee('data-rss-manager-summary', false)
        ->assertSee('data-rss-manager-priority', false)
        ->assertSee('data-rss-manager-activity', false)
        ->assertSee('data-rss-feed-card', false)
        ->assertSee('data-rss-filter-search', false)
        ->assertDontSee('data-rss-manager-results', false);
});

it('parses all feeds from the custom page', function () {
    $this->actingAs(filamentAdminUser());

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
    $this->actingAs(filamentAdminUser());

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
    $this->actingAs(filamentAdminUser());

    $feed = RssFeed::factory()->create(['is_active' => true]);

    Livewire::test(ManageRssFeeds::class)
        ->call('toggleFeed', $feed->id)
        ->assertSet('feeds.0.is_active', false);
});

it('summarizes filtered feed health on the custom page', function () {
    $this->actingAs(filamentAdminUser());

    $category = Category::factory()->create([
        'name' => 'Politics',
        'slug' => 'politics',
    ]);

    $otherCategory = Category::factory()->create([
        'name' => 'Sport',
        'slug' => 'sport',
    ]);

    RssFeed::factory()->create([
        'category_id' => $category->id,
        'is_active' => true,
        'next_parse_at' => now()->subMinutes(10),
        'articles_parsed_total' => 10,
        'last_run_new_count' => 2,
    ]);

    RssFeed::factory()->create([
        'category_id' => $category->id,
        'is_active' => true,
        'next_parse_at' => now()->addMinutes(15),
        'articles_parsed_total' => 4,
        'last_run_new_count' => 1,
        'last_error' => 'Timeout while fetching feed',
    ]);

    RssFeed::factory()->create([
        'category_id' => $category->id,
        'is_active' => false,
        'articles_parsed_total' => 3,
        'last_run_new_count' => 0,
    ]);

    RssFeed::factory()->create([
        'category_id' => $otherCategory->id,
        'is_active' => true,
        'articles_parsed_total' => 20,
        'last_run_new_count' => 5,
    ]);

    $component = Livewire::test(ManageRssFeeds::class)
        ->set('filterCategory', $category->slug);

    expect($component->instance()->summary)
        ->toMatchArray([
            'total_feeds' => 4,
            'filtered_feeds' => 3,
            'active_feeds' => 2,
            'due_feeds' => 1,
            'failing_feeds' => 1,
            'categories' => 1,
            'articles_parsed_total' => 17,
            'new_last_run_total' => 3,
        ]);
});

it('filters feeds by search, category, and status on the custom page', function () {
    $this->actingAs(filamentAdminUser());

    $economy = Category::factory()->create([
        'name' => 'Economy',
        'slug' => 'economy',
    ]);

    $sports = Category::factory()->create([
        'name' => 'Sports',
        'slug' => 'sports',
    ]);

    RssFeed::factory()->create([
        'category_id' => $economy->id,
        'title' => 'Economy Daily',
        'source_name' => 'Daily source',
        'is_active' => true,
        'next_parse_at' => now()->subMinutes(5),
    ]);

    RssFeed::factory()->create([
        'category_id' => $economy->id,
        'title' => 'Economy Stable',
        'source_name' => 'Stable source',
        'is_active' => true,
        'next_parse_at' => now()->addMinutes(30),
    ]);

    RssFeed::factory()->create([
        'category_id' => $sports->id,
        'title' => 'Sports Daily',
        'source_name' => 'Daily source',
        'is_active' => true,
        'next_parse_at' => now()->subMinutes(15),
    ]);

    $component = Livewire::test(ManageRssFeeds::class)
        ->set('search', 'daily')
        ->set('filterCategory', $economy->slug)
        ->set('status', 'due');

    expect($component->instance()->filteredFeeds)
        ->toHaveCount(1)
        ->and($component->instance()->filteredFeeds[0]['title'])->toBe('Economy Daily')
        ->and($component->instance()->activeFilters)
        ->toContain(
            ['label' => 'Поиск', 'value' => 'daily'],
            ['label' => 'Рубрика', 'value' => 'Economy'],
            ['label' => 'Статус', 'value' => 'Ждут запуска'],
        );
});
