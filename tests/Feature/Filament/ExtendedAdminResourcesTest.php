<?php

use App\Filament\Resources\ArticleViews\ArticleViewResource;
use App\Filament\Resources\ArticleViews\Pages\CreateArticleView;
use App\Filament\Resources\Bookmarks\BookmarkResource;
use App\Filament\Resources\Bookmarks\Pages\CreateBookmark;
use App\Filament\Resources\Metrics\MetricResource;
use App\Filament\Resources\Metrics\Pages\CreateMetric;
use App\Filament\Resources\NewsletterSubscribers\NewsletterSubscriberResource;
use App\Filament\Resources\NewsletterSubscribers\Pages\CreateNewsletterSubscriber;
use App\Filament\Resources\RssParseLogs\Pages\CreateRssParseLog;
use App\Filament\Resources\RssParseLogs\RssParseLogResource;
use App\Filament\Resources\SubCategories\Pages\CreateSubCategory;
use App\Filament\Resources\SubCategories\Pages\ListSubCategories;
use App\Models\Article;
use App\Models\ArticleView;
use App\Models\Bookmark;
use App\Models\Category;
use App\Models\NewsletterSubscriber;
use App\Models\RssFeed;
use App\Models\SubCategory;
use App\Models\User;
use Livewire\Livewire;

it('creates a subcategory with the page-based resource form', function () {
    $this->actingAs(User::factory()->create(['email_verified_at' => now()]));

    $category = Category::factory()->create();

    Livewire::test(CreateSubCategory::class)
        ->fillForm([
            'category_id' => $category->id,
            'name' => 'Рынок труда',
            'slug' => 'rynok-truda',
            'description' => 'Подрубрика для рынка труда.',
            'order' => 4,
            'is_active' => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors()
        ->assertNotified()
        ->assertRedirect();

    expect(SubCategory::query()->where('slug', 'rynok-truda')->exists())->toBeTrue();
});

it('sorts subcategories across the admin table columns', function () {
    $this->actingAs(User::factory()->create(['email_verified_at' => now()]));

    $alphaCategory = Category::factory()->create(['name' => 'Alpha category']);
    $betaCategory = Category::factory()->create(['name' => 'Beta category']);
    $gammaCategory = Category::factory()->create(['name' => 'Gamma category']);

    $zuluRecord = SubCategory::factory()->forCategory($gammaCategory)->create([
        'name' => 'Zulu topic',
        'slug' => 'zulu-topic',
        'order' => 30,
        'is_active' => false,
        'updated_at' => now()->subDays(3),
    ]);

    $alphaRecord = SubCategory::factory()->forCategory($alphaCategory)->create([
        'name' => 'Alpha topic',
        'slug' => 'alpha-topic',
        'order' => 10,
        'is_active' => true,
        'updated_at' => now()->subDays(2),
    ]);

    $echoRecord = SubCategory::factory()->forCategory($betaCategory)->create([
        'name' => 'Echo topic',
        'slug' => 'echo-topic',
        'order' => 20,
        'is_active' => false,
        'updated_at' => now()->subDay(),
    ]);

    Article::factory()->count(2)->forSubCategory($zuluRecord)->create();
    Article::factory()->count(1)->forSubCategory($echoRecord)->create();

    Livewire::test(ListSubCategories::class)
        ->assertTableColumnExists('category.name', fn ($column): bool => $column->isSortable())
        ->assertTableColumnExists('name', fn ($column): bool => $column->isSortable())
        ->assertTableColumnExists('slug', fn ($column): bool => $column->isSortable())
        ->assertTableColumnExists('articles_count', fn ($column): bool => $column->isSortable())
        ->assertTableColumnExists('is_active', fn ($column): bool => $column->isSortable())
        ->assertTableColumnExists('order', fn ($column): bool => $column->isSortable())
        ->assertTableColumnExists('updated_at', fn ($column): bool => $column->isSortable())
        ->assertCanSeeTableRecords([$alphaRecord, $echoRecord, $zuluRecord], inOrder: true)
        ->sortTable('name', 'asc')
        ->assertCanSeeTableRecords([$alphaRecord, $echoRecord, $zuluRecord], inOrder: true)
        ->sortTable('category.name', 'asc')
        ->assertCanSeeTableRecords([$alphaRecord, $echoRecord, $zuluRecord], inOrder: true)
        ->sortTable('articles_count', 'desc')
        ->assertCanSeeTableRecords([$zuluRecord, $echoRecord, $alphaRecord], inOrder: true)
        ->sortTable('is_active', 'desc')
        ->assertCanSeeTableRecords([$alphaRecord, $echoRecord], inOrder: true)
        ->sortTable('order', 'asc')
        ->assertCanSeeTableRecords([$alphaRecord, $echoRecord, $zuluRecord], inOrder: true)
        ->toggleAllTableColumns()
        ->sortTable('slug', 'asc')
        ->assertCanSeeTableRecords([$alphaRecord, $echoRecord, $zuluRecord], inOrder: true)
        ->sortTable('updated_at', 'desc')
        ->assertCanSeeTableRecords([$echoRecord, $alphaRecord, $zuluRecord], inOrder: true);
});

it('loads admin relations for article views and bookmarks', function () {
    $article = Article::factory()->create([
        'category_id' => Category::factory()->create()->id,
    ]);

    $view = ArticleView::factory()->forArticle($article)->create();
    $bookmark = Bookmark::factory()->forArticle($article)->create();

    $viewRecord = ArticleViewResource::getEloquentQuery()->whereKey($view->id)->first();
    $bookmarkRecord = BookmarkResource::getEloquentQuery()->whereKey($bookmark->id)->first();

    expect($viewRecord)
        ->not()->toBeNull()
        ->and($viewRecord?->relationLoaded('article'))->toBeTrue()
        ->and($bookmarkRecord)
        ->not()->toBeNull()
        ->and($bookmarkRecord?->relationLoaded('article'))->toBeTrue();
});

it('creates a newsletter subscriber with category interests', function () {
    $this->actingAs(User::factory()->create(['email_verified_at' => now()]));

    $categories = Category::factory()->count(2)->create();

    Livewire::test(CreateNewsletterSubscriber::class)
        ->fillForm([
            'email' => 'reader@example.com',
            'name' => 'Reader',
            'category_ids' => $categories->pluck('id')->all(),
            'confirmed' => true,
            'confirmed_at' => now()->toDateTimeString(),
            'country_code' => 'LT',
            'timezone' => 'Europe/Vilnius',
            'locale' => 'ru',
        ])
        ->call('create')
        ->assertHasNoFormErrors()
        ->assertNotified()
        ->assertRedirect();

    $subscriber = NewsletterSubscriber::query()->where('email', 'reader@example.com')->first();

    expect($subscriber)
        ->not()->toBeNull()
        ->and($subscriber?->preferredCategoryIds())->toBe($categories->pluck('id')->all());
});

it('creates an article view, bookmark, parse log, and metric through admin pages', function () {
    $this->actingAs(User::factory()->create(['email_verified_at' => now()]));

    $category = Category::factory()->create();
    $article = Article::factory()->create(['category_id' => $category->id]);
    $feed = RssFeed::factory()->create(['category_id' => $category->id]);

    Livewire::test(CreateArticleView::class)
        ->fillForm([
            'article_id' => $article->id,
            'viewed_at' => now()->toDateTimeString(),
            'device_type' => 'desktop',
            'referrer_type' => 'direct',
            'country_code' => 'LT',
            'locale' => 'ru',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    Livewire::test(CreateBookmark::class)
        ->fillForm([
            'article_id' => $article->id,
            'session_hash' => hash('sha256', 'bookmark-session'),
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    Livewire::test(CreateRssParseLog::class)
        ->fillForm([
            'rss_feed_id' => $feed->id,
            'triggered_by' => 'filament',
            'started_at' => now()->subMinute()->toDateTimeString(),
            'finished_at' => now()->toDateTimeString(),
            'duration_ms' => 800,
            'success' => false,
            'new_count' => 4,
            'skip_count' => 2,
            'error_count' => 1,
            'total_items' => 7,
            'error_message' => 'Timeout',
            'item_errors' => ['Item 1 failed'],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    Livewire::test(CreateMetric::class)
        ->fillForm([
            'name' => 'custom_metric',
            'category' => 'admin',
            'measurable_type' => Article::class,
            'measurable_id' => $article->id,
            'bucket_start' => now()->toDateTimeString(),
            'bucket_date' => now()->toDateString(),
            'value' => 7,
            'fingerprint' => 'metric-'.uniqid(),
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(ArticleView::query()->where('article_id', $article->id)->exists())->toBeTrue()
        ->and(Bookmark::query()->where('article_id', $article->id)->exists())->toBeTrue()
        ->and(RssParseLogResource::getEloquentQuery()->where('rss_feed_id', $feed->id)->exists())->toBeTrue()
        ->and(MetricResource::getEloquentQuery()->where('measurable_id', $article->id)->exists())->toBeTrue()
        ->and(NewsletterSubscriberResource::getUrl('index'))->toContain('/admin');
});
