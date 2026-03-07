<?php

use App\Filament\Widgets\FeedStatusWidget;
use App\Filament\Widgets\LatestArticlesWidget;
use App\Filament\Widgets\ParseLogsWidget;
use App\Filament\Widgets\StatsOverviewWidget;
use App\Filament\Widgets\ViewsChartWidget;
use App\Models\Article;
use App\Models\ArticleView;
use App\Models\RssFeed;
use App\Models\RssParseLog;
use App\Models\Tag;
use App\Models\User;
use App\Providers\Filament\AdminPanelProvider;
use App\Services\RssParserService;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Filament\Panel;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Livewire\Livewire;

function invokeWidgetMethod(string $widgetClass, string $method): mixed
{
    $reflection = new ReflectionMethod($widgetClass, $method);
    $reflection->setAccessible(true);

    return $reflection->invoke(app($widgetClass));
}

beforeEach(function () {
    $provider = new AdminPanelProvider(app());

    Filament::setCurrentPanel($provider->panel(new Panel));
});

afterEach(function () {
    \Mockery::close();
});

it('registers the dashboard widgets in the configured order', function () {
    $panel = (new AdminPanelProvider(app()))->panel(new Panel);

    expect(array_values($panel->getWidgets()))->toBe([
        StatsOverviewWidget::class,
        ViewsChartWidget::class,
        LatestArticlesWidget::class,
        FeedStatusWidget::class,
        ParseLogsWidget::class,
    ]);
});

it('builds the stats overview widget metrics', function () {
    $todayArticle = Article::withoutSyncingToSearch(function (): Article {
        return Article::factory()->create([
            'status' => 'published',
            'published_at' => now(),
            'views_count' => 120,
        ]);
    });

    Article::withoutSyncingToSearch(function (): Article {
        return Article::factory()->create([
            'status' => 'published',
            'published_at' => now()->subDay(),
            'views_count' => 30,
        ]);
    });

    ArticleView::factory()->create([
        'article_id' => $todayArticle->id,
        'viewed_at' => now(),
    ]);

    RssFeed::factory()->create([
        'is_active' => true,
    ]);

    Tag::factory()->create([
        'is_trending' => true,
    ]);

    /** @var array<int, Stat> $stats */
    $stats = invokeWidgetMethod(StatsOverviewWidget::class, 'getStats');

    expect(array_map(fn (Stat $stat): string => (string) $stat->getLabel(), $stats))->toBe([
        'Total Published',
        'Today',
        'Views Today',
        'Total Views',
        'Active Feeds',
        'Trending Tags',
    ])
        ->and($stats[0]->getValue())->toBe(2)
        ->and(count($stats[0]->getChart() ?? []))->toBe(7)
        ->and($stats[1]->getValue())->toBe(1)
        ->and($stats[2]->getValue())->toBe(1)
        ->and($stats[3]->getValue())->toBe('150')
        ->and($stats[4]->getValue())->toBe(1)
        ->and($stats[5]->getValue())->toBe(1);
});

it('renders the latest articles widget rows', function () {
    $this->actingAs(User::factory()->create());

    $article = Article::withoutSyncingToSearch(function (): Article {
        return Article::factory()->create([
            'status' => 'published',
            'published_at' => now(),
            'title' => 'Последняя новость портала',
        ]);
    });

    Livewire::test(LatestArticlesWidget::class)
        ->assertSee('Последние статьи')
        ->assertSee('Последняя новость портала')
        ->assertSee($article->category->name);
});

it('parses a feed from the feed status widget action', function () {
    $this->actingAs(User::factory()->create());

    $feed = RssFeed::factory()->create([
        'title' => 'Sport feed',
    ]);

    $parser = \Mockery::mock(RssParserService::class);
    $parser->shouldReceive('parseFeed')
        ->once()
        ->with(\Mockery::type(RssFeed::class), 'filament')
        ->andReturn([
            'feed' => $feed->title,
            'new' => 3,
            'skip' => 1,
            'errors' => 0,
            'error' => null,
        ]);

    app()->instance(RssParserService::class, $parser);

    Livewire::test(FeedStatusWidget::class)
        ->assertSee('Sport feed')
        ->callAction(TestAction::make('parse')->table($feed))
        ->assertNotified('Parse Complete');
});

it('renders the parse logs widget rows', function () {
    $this->actingAs(User::factory()->create());

    $feed = RssFeed::factory()->create([
        'title' => 'Politics feed',
    ]);

    RssParseLog::factory()->create([
        'rss_feed_id' => $feed->id,
        'duration_ms' => 850,
        'new_count' => 4,
        'success' => true,
    ]);

    Livewire::test(ParseLogsWidget::class)
        ->assertSee('История парсинга')
        ->assertSee('Politics feed')
        ->assertSee('850 ms');
});

it('builds the views chart widget dataset', function () {
    ArticleView::factory()->count(2)->create([
        'viewed_at' => now()->subDay(),
    ]);

    ArticleView::factory()->create([
        'viewed_at' => now()->subDays(2),
    ]);

    $data = invokeWidgetMethod(ViewsChartWidget::class, 'getData');
    $type = invokeWidgetMethod(ViewsChartWidget::class, 'getType');

    expect($type)->toBe('line')
        ->and($data['datasets'][0]['label'])->toBe('Просмотры')
        ->and($data['datasets'][0]['fill'])->toBeTrue()
        ->and($data['datasets'][0]['tension'])->toBe(0.4)
        ->and($data['labels'])->toHaveCount(2)
        ->and($data['datasets'][0]['data'])->toBe([1, 2]);
});
