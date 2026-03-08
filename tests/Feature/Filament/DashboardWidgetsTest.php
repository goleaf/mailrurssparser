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
use App\Services\MetricTracker;
use App\Services\RssParserService;
use App\Services\TrackedMetric;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Filament\Panel;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;
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
        'country_code' => 'DE',
        'timezone' => 'Europe/Berlin',
        'viewed_at' => now(),
    ]);

    RssFeed::factory()->create([
        'is_active' => true,
    ]);

    Tag::factory()->create([
        'is_trending' => true,
    ]);

    app(MetricTracker::class)->record(TrackedMetric::BookmarkAdded, 2, $todayArticle);
    app(MetricTracker::class)->record(TrackedMetric::NewsletterSubscription, 3);
    app(MetricTracker::class)->record(TrackedMetric::RssArticleImported, 4, RssFeed::query()->first());
    DB::table(config('threat-detection.table_name', 'threat_logs'))->insert([
        [
            'ip_address' => '203.0.113.10',
            'url' => url('/?q=%3Cscript%3E'),
            'user_agent' => 'Pest',
            'type' => '[middleware] Encoded XSS Detected',
            'payload' => 'QUERY: {"q":"%3Cscript%3E"}',
            'threat_level' => 'high',
            'confidence_score' => 80,
            'confidence_label' => 'high',
            'is_false_positive' => false,
            'action_taken' => 'logged',
            'created_at' => now(),
            'updated_at' => now(),
        ],
        [
            'ip_address' => '203.0.113.11',
            'url' => url('/api/v1/stats/overview?scan=1'),
            'user_agent' => 'Pest',
            'type' => '[middleware] Security Scanner Detected',
            'payload' => 'QUERY: {"scan":"1"}',
            'threat_level' => 'low',
            'confidence_score' => 45,
            'confidence_label' => 'medium',
            'is_false_positive' => false,
            'action_taken' => 'logged',
            'created_at' => now(),
            'updated_at' => now(),
        ],
    ]);

    /** @var array<int, Stat> $stats */
    $stats = invokeWidgetMethod(StatsOverviewWidget::class, 'getStats');

    expect(array_map(fn (Stat $stat): string => (string) $stat->getLabel(), $stats))->toBe([
        'Total Published',
        'Today',
        'Views Today',
        'Total Views',
        'Top Country 7d',
        'Top TZ 7d',
        'Threats 24h',
        'Active Feeds',
        'Trending Tags',
        'Bookmarks 24h',
        'Subscriptions 7d',
        'RSS Imports 24h',
    ])
        ->and($stats[0]->getValue())->toBe(2)
        ->and(count($stats[0]->getChart() ?? []))->toBe(7)
        ->and($stats[1]->getValue())->toBe(1)
        ->and($stats[2]->getValue())->toBe(1)
        ->and($stats[3]->getValue())->toBe('150')
        ->and($stats[4]->getValue())->toBe('DE')
        ->and($stats[5]->getValue())->toBe('Europe/Berlin')
        ->and($stats[6]->getValue())->toBe(2)
        ->and($stats[6]->getDescription())->toBe('1 high severity')
        ->and($stats[7]->getValue())->toBe(1)
        ->and($stats[8]->getValue())->toBe(1)
        ->and($stats[9]->getValue())->toBe(2)
        ->and($stats[10]->getValue())->toBe(3)
        ->and($stats[11]->getValue())->toBe(4);
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
