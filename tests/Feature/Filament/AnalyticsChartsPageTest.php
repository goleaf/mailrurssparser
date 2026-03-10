<?php

use App\Filament\Pages\ChartsPage;
use App\Filament\Support\AdminNavigationGroup;
use App\Filament\Widgets\Charts\DailyViewsChartWidget;
use App\Filament\Widgets\Charts\RssFeedParseActivityWidget;
use App\Models\Article;
use App\Models\ArticleView;
use App\Models\NewsletterSubscriber;
use App\Models\RssFeed;
use App\Models\RssParseLog;
use App\Models\Tag;
use App\Providers\Filament\AdminPanelProvider;
use Filament\Facades\Filament;
use Filament\Panel;

beforeEach(function () {
    Filament::setCurrentPanel((new AdminPanelProvider(app()))->panel(new Panel));
});

it('registers the analytics charts page in the analytics navigation group', function () {
    expect(ChartsPage::getNavigationGroup())
        ->toBe(AdminNavigationGroup::Analytics)
        ->and(ChartsPage::getNavigationIcon())
        ->not()->toBeNull()
        ->and(ChartsPage::getNavigationLabel())
        ->toBe('Графики и аналитика');
});

it('registers the apex charts widgets on the admin dashboard after the existing widgets', function () {
    $panel = Filament::getCurrentPanel();

    expect(array_slice(array_values($panel->getWidgets()), -2))->toBe([
        DailyViewsChartWidget::class,
        RssFeedParseActivityWidget::class,
    ]);
});

it('renders the analytics charts page with the new apex widgets', function () {
    $this->actingAs(filamentAdminUser());

    $article = Article::withoutSyncingToSearch(function (): Article {
        return Article::factory()->create([
            'status' => 'published',
            'published_at' => now()->subDay(),
            'title' => 'Analytics story',
        ]);
    });

    $article->tags()->attach(Tag::factory()->create(['name' => 'Analytics'])->id);

    ArticleView::factory()->count(3)->create([
        'article_id' => $article->id,
        'viewed_at' => now()->subDay(),
    ]);

    $feed = RssFeed::factory()->create([
        'title' => 'Analytics feed',
    ]);

    RssParseLog::factory()->create([
        'rss_feed_id' => $feed->id,
        'started_at' => now()->subDay(),
        'finished_at' => now()->subDay()->addMinute(),
        'success' => true,
    ]);

    NewsletterSubscriber::factory()->create([
        'confirmed' => true,
        'confirmed_at' => now()->subDays(2),
    ]);

    $this->get(route('filament.admin.pages.charts'))
        ->assertSuccessful()
        ->assertSeeText('Графики и аналитика')
        ->assertSeeText('Ежедневные просмотры')
        ->assertSeeText('Динамика публикаций')
        ->assertSeeText('Активность RSS-парсинга')
        ->assertSeeText('Распределение по рубрикам')
        ->assertSeeText('Топ тегов')
        ->assertSeeText('Рост подписчиков')
        ->assertSeeText('Клики по соцсетям');
});
