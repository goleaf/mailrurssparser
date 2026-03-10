<?php

use App\Filament\Resources\RssFeeds\Pages\CreateRssFeed;
use App\Filament\Resources\RssFeeds\Pages\ListRssFeeds;
use App\Filament\Resources\RssFeeds\Pages\ViewRssFeed;
use App\Models\RssFeed;
use App\Models\RssParseLog;
use App\Services\RssParserService;
use Filament\Actions\Testing\TestAction;
use Livewire\Livewire;

afterEach(function () {
    \Mockery::close();
});

function configuredFeedUrl(string $path): string
{
    return rtrim((string) config('rss.feed_origin'), '/').'/rss/'.$path.'/';
}

it('parses a feed from the table action', function () {
    $this->actingAs(filamentAdminUser());

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
    $this->actingAs(filamentAdminUser());

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
    $this->actingAs(filamentAdminUser());

    Livewire::test(CreateRssFeed::class)
        ->fillForm([
            'category_id' => \App\Models\Category::factory()->create()->id,
            'title' => 'Главные новости',
            'url' => configuredFeedUrl('main'),
            'source_name' => 'РИА Новости',
            'is_active' => true,
            'auto_publish' => true,
            'auto_featured' => false,
            'fetch_interval' => 15,
            'extra_settings_rows' => [
                ['key' => 'status', 'value' => 'pending'],
                ['key' => 'content_type', 'value' => 'analysis'],
                ['key' => 'sub_category_name', 'value' => 'Отрасли'],
                ['key' => 'sub_category_slug', 'value' => 'otrasli'],
                ['key' => 'short_description_length', 'value' => '180'],
                ['key' => 'source_page_enabled', 'value' => '1'],
                ['key' => 'source_page_article_selector', 'value' => '.story-body, article'],
                ['key' => 'source_page_remove_selectors', 'value' => '.share, .related'],
            ],
        ])
        ->call('create')
        ->assertHasNoFormErrors()
        ->assertNotified()
        ->assertRedirect();

    $feed = RssFeed::query()->where('url', configuredFeedUrl('main'))->first();

    expect($feed)
        ->not()->toBeNull()
        ->and($feed?->source_name)->toBe('РИА Новости')
        ->and($feed?->fetch_interval)->toBe(15)
        ->and($feed?->auto_publish)->toBeTrue()
        ->and($feed?->extra_settings)->toBe([
            'status' => 'pending',
            'content_type' => 'analysis',
            'sub_category_name' => 'Отрасли',
            'sub_category_slug' => 'otrasli',
            'short_description_length' => 180,
            'source_page_enabled' => 1,
            'source_page_article_selector' => '.story-body, article',
            'source_page_remove_selectors' => '.share, .related',
        ]);
});

it('renders the rss feeds dashboard with attention signals and recent parse logs', function () {
    $this->actingAs(filamentAdminUser());

    $feed = RssFeed::factory()->create([
        'title' => 'Politics feed',
        'last_error' => 'Feed timed out',
    ]);

    RssParseLog::factory()->create([
        'rss_feed_id' => $feed->id,
        'new_count' => 4,
        'duration_ms' => 850,
        'success' => true,
    ]);

    Livewire::test(ListRssFeeds::class)
        ->assertSee('Контур RSS-лент')
        ->assertSee('Сигналы и приоритеты')
        ->assertSee('Журнал последних запусков')
        ->assertSee('Каталог и настройки лент')
        ->assertSee('Politics feed')
        ->assertSee('Feed timed out')
        ->assertSee('850 ms');
});

it('renders the redesigned rss feeds index page over http', function () {
    $this->actingAs(filamentAdminUser(['email_verified_at' => now()]));

    $feed = RssFeed::factory()->create([
        'title' => 'Economy feed',
        'last_error' => 'Source unavailable',
    ]);

    RssParseLog::factory()->create([
        'rss_feed_id' => $feed->id,
        'success' => false,
    ]);

    $this->get(route('filament.admin.resources.rss-feeds.index'))
        ->assertSuccessful()
        ->assertSeeText('Контур RSS-лент')
        ->assertSeeText('Каталог и настройки лент')
        ->assertSeeText('Журнал последних запусков')
        ->assertSeeText('Economy feed')
        ->assertSee('data-rss-feeds-overview', false)
        ->assertSee('data-rss-feeds-attention', false)
        ->assertSee('data-rss-feeds-table-shell', false);
});

it('renders the rss feeds empty state when no feeds exist', function () {
    $this->actingAs(filamentAdminUser());

    Livewire::test(ListRssFeeds::class)
        ->assertSee('RSS-ленты ещё не настроены')
        ->assertSee('Добавьте первую ленту, чтобы запустить парсинг и наполнить портал новостями.')
        ->assertSee('Добавить RSS-ленту');
});

it('shows a feed summary and recent parse runs on the view page', function () {
    $this->actingAs(filamentAdminUser());

    $feed = RssFeed::factory()->create([
        'title' => 'Politics feed',
        'extra_settings' => [
            'status' => 'pending',
            'source_name' => 'Custom Source',
        ],
    ]);

    RssParseLog::factory()->create([
        'rss_feed_id' => $feed->id,
        'new_count' => 4,
        'skip_count' => 2,
        'error_count' => 1,
        'duration_ms' => 850,
        'success' => false,
        'error_message' => 'HTTP 500',
        'triggered_by' => 'manual',
    ]);

    Livewire::test(ViewRssFeed::class, [
        'record' => $feed->getRouteKey(),
    ])
        ->assertOk()
        ->assertSee('Обзор ленты')
        ->assertSee('Politics feed')
        ->assertSee('Статей в базе')
        ->assertSee('Запусков в журнале')
        ->assertSee('Переопределения ленты')
        ->assertSee('status')
        ->assertSee('Custom Source')
        ->assertSee('Последние запуски парсинга')
        ->assertSee('Вручную')
        ->assertSee('850 ms')
        ->assertSee('Сбой')
        ->assertSee('HTTP 500');
});

it('shows a custom empty state on the feed view page before any parse runs exist', function () {
    $this->actingAs(filamentAdminUser());

    $feed = RssFeed::factory()->create();

    Livewire::test(ViewRssFeed::class, [
        'record' => $feed->getRouteKey(),
    ])
        ->assertOk()
        ->assertSee('Запусков парсинга ещё не было')
        ->assertSee('Запустите эту ленту вручную или дождитесь первого запуска по расписанию.');
});
