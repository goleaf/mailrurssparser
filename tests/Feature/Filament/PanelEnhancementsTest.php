<?php

use App\Filament\Pages\ChartsPage;
use App\Filament\Pages\ManageRssFeeds;
use App\Filament\Pages\ParseHistory;
use App\Filament\Resources\Articles\ArticleResource;
use App\Filament\Resources\Articles\Pages\EditArticle;
use App\Filament\Resources\Articles\Pages\ListArticles;
use App\Filament\Resources\ArticleViews\ArticleViewResource;
use App\Filament\Resources\ArticleViews\Pages\ListArticleViews;
use App\Filament\Resources\Bookmarks\BookmarkResource;
use App\Filament\Resources\Bookmarks\Pages\ListBookmarks;
use App\Filament\Resources\Categories\CategoryResource;
use App\Filament\Resources\Categories\Pages\ListCategories;
use App\Filament\Resources\Metrics\MetricResource;
use App\Filament\Resources\Metrics\Pages\ListMetrics;
use App\Filament\Resources\NewsletterSubscribers\NewsletterSubscriberResource;
use App\Filament\Resources\NewsletterSubscribers\Pages\ListNewsletterSubscribers;
use App\Filament\Resources\RssFeeds\Pages\ListRssFeeds;
use App\Filament\Resources\RssFeeds\RssFeedResource;
use App\Filament\Resources\RssParseLogs\Pages\ListRssParseLogs;
use App\Filament\Resources\RssParseLogs\Pages\ViewRssParseLog;
use App\Filament\Resources\RssParseLogs\RssParseLogResource;
use App\Filament\Resources\SubCategories\Pages\ListSubCategories;
use App\Filament\Resources\SubCategories\SubCategoryResource;
use App\Filament\Resources\Tags\Pages\EditTag;
use App\Filament\Resources\Tags\Pages\ListTags;
use App\Filament\Resources\Tags\TagResource;
use App\Filament\Support\AdminNavigationGroup;
use App\Models\Article;
use App\Models\Category;
use App\Models\RssFeed;
use App\Models\RssParseLog;
use App\Models\Tag;
use App\Providers\Filament\AdminPanelProvider;
use App\Services\ArticleStatus;
use Awcodes\Curator\CuratorPlugin;
use Awcodes\StickyHeader\StickyHeaderPlugin;
use Filament\Enums\ThemeMode;
use Filament\Facades\Filament;
use Filament\Panel;
use Filament\Schemas\Components\Tabs;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Enums\Width;
use Leandrocfe\FilamentApexCharts\FilamentApexChartsPlugin;
use Livewire\Livewire;

beforeEach(function () {
    Filament::setCurrentPanel((new AdminPanelProvider(app()))->panel(new Panel));
});

dataset('cms_list_pages', [
    ListArticles::class,
    ListCategories::class,
    ListTags::class,
    ListRssFeeds::class,
]);

dataset('admin_table_columns', [
    'articles table' => [
        ListArticles::class,
        ['title', 'category.name', 'subCategory.name', 'rssFeed.title', 'editor.name', 'tags_summary', 'content_type', 'status', 'is_featured', 'is_breaking', 'is_pinned', 'importance', 'views_count', 'bookmarked_by_count', 'related_articles_count', 'published_at'],
        ['title', 'category.name', 'subCategory.name', 'rssFeed.title', 'editor.name', 'tags_summary', 'content_type', 'status', 'is_featured', 'is_breaking', 'is_pinned', 'importance', 'views_count', 'bookmarked_by_count', 'related_articles_count', 'published_at'],
    ],
    'article views table' => [
        ListArticleViews::class,
        ['article.title', 'article.category.name', 'device_type', 'referrer_type', 'country_code', 'locale', 'referrer_domain', 'session_hash', 'viewed_at'],
        ['article.title', 'article.category.name', 'device_type', 'referrer_type', 'country_code', 'locale', 'referrer_domain', 'session_hash', 'viewed_at'],
    ],
    'bookmarks table' => [
        ListBookmarks::class,
        ['article.title', 'article.category.name', 'article.subCategory.name', 'session_hash', 'created_at'],
        ['article.title', 'article.category.name', 'article.subCategory.name', 'session_hash', 'created_at'],
    ],
    'categories table' => [
        ListCategories::class,
        ['icon', 'name', 'slug', 'rss_key', 'color', 'order', 'is_active', 'show_in_menu', 'sub_categories_count', 'rss_feeds_count', 'articles_count_cache'],
        ['icon', 'name', 'slug', 'rss_key', 'color', 'order', 'is_active', 'show_in_menu', 'sub_categories_count', 'rss_feeds_count', 'articles_count_cache'],
    ],
    'metrics table' => [
        ListMetrics::class,
        ['name', 'category', 'measurable_type', 'measurable_id', 'value', 'bucket_start', 'bucket_date', 'fingerprint'],
        ['name', 'category', 'measurable_type', 'measurable_id', 'value', 'bucket_start', 'bucket_date', 'fingerprint'],
    ],
    'newsletter subscribers table' => [
        ListNewsletterSubscribers::class,
        ['email', 'name', 'categories_summary', 'confirmed', 'confirmed_at', 'unsubscribed_at', 'country_code', 'timezone', 'locale', 'created_at'],
        ['email', 'name', 'categories_summary', 'confirmed', 'confirmed_at', 'unsubscribed_at', 'country_code', 'timezone', 'locale', 'created_at'],
    ],
    'rss feeds table' => [
        ListRssFeeds::class,
        ['title', 'source_name', 'category.name', 'is_active', 'auto_publish', 'auto_featured', 'articles_count', 'parse_logs_count', 'fetch_interval', 'last_parsed_at', 'next_parse_at', 'last_run_new_count', 'consecutive_failures', 'last_error'],
        ['title', 'source_name', 'category.name', 'is_active', 'auto_publish', 'auto_featured', 'articles_count', 'parse_logs_count', 'fetch_interval', 'last_parsed_at', 'next_parse_at', 'last_run_new_count', 'consecutive_failures', 'last_error'],
    ],
    'rss parse logs table' => [
        ListRssParseLogs::class,
        ['rssFeed.title', 'rssFeed.category.name', 'started_at', 'duration_ms', 'new_count', 'skip_count', 'error_count', 'success', 'triggered_by', 'error_message'],
        ['rssFeed.title', 'rssFeed.category.name', 'started_at', 'duration_ms', 'new_count', 'skip_count', 'error_count', 'success', 'triggered_by', 'error_message'],
    ],
    'subcategories table' => [
        ListSubCategories::class,
        ['color', 'category.name', 'name', 'slug', 'articles_count', 'is_active', 'order', 'updated_at'],
        ['color', 'category.name', 'name', 'slug', 'articles_count', 'is_active', 'order', 'created_at', 'updated_at'],
    ],
    'tags table' => [
        ListTags::class,
        ['name', 'slug', 'description', 'color', 'articles_count', 'usage_count', 'is_trending', 'is_featured'],
        ['name', 'slug', 'description', 'color', 'articles_count', 'usage_count', 'is_trending', 'is_featured'],
    ],
]);

dataset('grouped_navigation_items', [
    'article resource' => [ArticleResource::class, AdminNavigationGroup::Editorial],
    'category resource' => [CategoryResource::class, AdminNavigationGroup::Taxonomy],
    'tag resource' => [TagResource::class, AdminNavigationGroup::Taxonomy],
    'subcategory resource' => [SubCategoryResource::class, AdminNavigationGroup::Taxonomy],
    'rss feed resource' => [RssFeedResource::class, AdminNavigationGroup::Ingestion],
    'rss parse log resource' => [RssParseLogResource::class, AdminNavigationGroup::Ingestion],
    'manage rss feeds page' => [ManageRssFeeds::class, AdminNavigationGroup::Ingestion],
    'parse history page' => [ParseHistory::class, AdminNavigationGroup::Ingestion],
    'newsletter subscriber resource' => [NewsletterSubscriberResource::class, AdminNavigationGroup::Audience],
    'article view resource' => [ArticleViewResource::class, AdminNavigationGroup::Audience],
    'bookmark resource' => [BookmarkResource::class, AdminNavigationGroup::Audience],
    'metric resource' => [MetricResource::class, AdminNavigationGroup::Analytics],
    'charts page' => [ChartsPage::class, AdminNavigationGroup::Analytics],
]);

it('registers configured article resource views for the admin panel', function () {
    $panel = Filament::getCurrentPanel();

    expect($panel->getResourceConfiguration(ArticleResource::class, 'moderation'))
        ->not()->toBeNull()
        ->and($panel->getResourceConfiguration(ArticleResource::class, 'published'))
        ->not()->toBeNull()
        ->and(ArticleResource::withConfiguration('moderation', fn () => ArticleResource::getNavigationLabel()))
        ->toBe('Очередь модерации')
        ->and(ArticleResource::withConfiguration('published', fn () => ArticleResource::getNavigationLabel()))
        ->toBe('Опубликованные статьи')
        ->and(ArticleResource::withConfiguration('moderation', fn () => ArticleResource::getNavigationGroup()))
        ->toBe(AdminNavigationGroup::Editorial)
        ->and(str_ends_with(ArticleResource::getUrl(configuration: 'moderation'), '/admin/moderation-queue'))
        ->toBeTrue()
        ->and(str_ends_with(ArticleResource::getUrl(configuration: 'published'), '/admin/published-articles'))
        ->toBeTrue();
});

it('uses a light-only full-width admin panel shell', function () {
    $panel = Filament::getCurrentPanel();

    expect($panel->hasDarkMode())
        ->toBeFalse()
        ->and($panel->getDefaultThemeMode())
        ->toBe(ThemeMode::Light)
        ->and($panel->getMaxContentWidth())
        ->toBe(Width::Full)
        ->and($panel->getSimplePageMaxContentWidth())
        ->toBe(Width::Full);
});

it('registers the sticky header and apex charts plugins plus the admin panel theme', function () {
    $panel = Filament::getCurrentPanel();
    $curatorPlugin = $panel->getPlugin('awcodes/curator');

    expect($panel->hasPlugin('awcodes-sticky-header'))
        ->toBeTrue()
        ->and($panel->getPlugin('awcodes-sticky-header'))
        ->toBeInstanceOf(StickyHeaderPlugin::class)
        ->and($panel->hasPlugin('filament-apex-charts'))
        ->toBeTrue()
        ->and($panel->getPlugin('filament-apex-charts'))
        ->toBeInstanceOf(FilamentApexChartsPlugin::class)
        ->and($panel->hasPlugin('awcodes/curator'))
        ->toBeTrue()
        ->and($panel->getPlugin('awcodes/curator'))
        ->toBeInstanceOf(CuratorPlugin::class)
        ->and($curatorPlugin->getLabel())
        ->toBe('Media')
        ->and($curatorPlugin->getPluralLabel())
        ->toBe('Media Library')
        ->and($curatorPlugin->getNavigationGroup())
        ->toBe('Media')
        ->and($curatorPlugin->getNavigationSort())
        ->toBe(10)
        ->and($curatorPlugin->shouldRegisterNavigation())
        ->toBeTrue()
        ->and($curatorPlugin->shouldShowBadge())
        ->toBeTrue()
        ->and($panel->getViteTheme())
        ->toBe('resources/css/filament/admin/theme.css');
});

it('keeps admin navigation groups iconized and labeled through the enum', function () {
    $contracts = class_implements(AdminNavigationGroup::class);

    expect($contracts)
        ->toHaveKey(HasLabel::class)
        ->not()->toHaveKey(HasIcon::class)
        ->and(array_key_exists(HasIcon::class, $contracts))
        ->toBeFalse();
});

it('keeps grouped navigation items iconized after removing group icons', function (
    string $navigationItemClass,
    AdminNavigationGroup $navigationGroup,
) {
    expect($navigationItemClass::getNavigationGroup())
        ->toBe($navigationGroup)
        ->and($navigationItemClass::getNavigationIcon())
        ->not()->toBeNull();
})->with('grouped_navigation_items');

it('renders the admin dashboard without group and item icon conflicts', function () {
    $this->actingAs(filamentAdminUser());

    $this->get(route('filament.admin.pages.dashboard'))
        ->assertSuccessful();
});

it('scopes configured article resource queries by status', function () {
    Article::withoutSyncingToSearch(function (): void {
        Article::factory()->create(['status' => 'draft']);
        Article::factory()->create(['status' => 'pending']);
        Article::factory()->create(['status' => 'published']);
    });

    $moderationStatuses = ArticleResource::withConfiguration('moderation', fn (): array => ArticleResource::getEloquentQuery()
        ->pluck('status')
        ->map(fn (ArticleStatus|string $status): string => $status instanceof ArticleStatus ? $status->value : $status)
        ->unique()
        ->values()
        ->all());

    $publishedStatuses = ArticleResource::withConfiguration('published', fn (): array => ArticleResource::getEloquentQuery()
        ->pluck('status')
        ->map(fn (ArticleStatus|string $status): string => $status instanceof ArticleStatus ? $status->value : $status)
        ->unique()
        ->values()
        ->all());

    expect($moderationStatuses)
        ->toBe(['pending'])
        ->and($publishedStatuses)
        ->toBe(['published']);
});

it('keeps cms list pages free from modal column managers', function (string $pageClass) {
    $this->actingAs(filamentAdminUser());

    $table = Livewire::test($pageClass)->instance()->getTable();

    expect($table->hasColumnManager())
        ->toBeFalse();
})->with('cms_list_pages');

it('keeps admin resource tables searchable and sortable across their configured columns', function (
    string $pageClass,
    array $searchableColumns,
    array $sortableColumns,
) {
    $this->actingAs(filamentAdminUser());

    $livewire = Livewire::test($pageClass);

    foreach ($searchableColumns as $columnName) {
        $livewire->assertTableColumnExists($columnName, fn ($column): bool => $column->isSearchable());
    }

    foreach ($sortableColumns as $columnName) {
        $livewire->assertTableColumnExists($columnName, fn ($column): bool => $column->isSortable());
    }
})->with('admin_table_columns');

it('keeps the dense article table configurable through toggleable columns instead of modal tools', function () {
    $this->actingAs(filamentAdminUser());

    $table = Livewire::test(ListArticles::class)->instance()->getTable();

    expect($table->hasColumnManager())
        ->toBeFalse()
        ->and($table->getColumn('is_featured')->isToggleable())
        ->toBeTrue()
        ->and($table->getColumn('views_count')->isToggledHiddenByDefault())
        ->toBeTrue()
        ->and($table->getColumn('tags_summary')->isToggledHiddenByDefault())
        ->toBeTrue();
});

it('renders curator-aware article image columns in the admin table', function () {
    $this->actingAs(filamentAdminUser());

    Livewire::test(ListArticles::class)
        ->assertTableColumnExists('featured_image')
        ->assertTableColumnExists('curatorMedia');
});

it('marks the article classification tab badge as deferred for existing records', function () {
    $this->actingAs(filamentAdminUser());

    $tag = Tag::factory()->create();

    $article = Article::withoutSyncingToSearch(function () use ($tag): Article {
        $article = Article::factory()->create();
        $article->tags()->sync([$tag->id]);

        return $article;
    });

    $page = Livewire::test(EditArticle::class, ['record' => $article->getRouteKey()])->instance();
    $schema = $page->getSchema('form');

    $tabs = $schema->getComponents()[0];
    $contentTab = $tabs->getChildSchema()->getComponents()[0];
    $classificationTab = $tabs->getChildSchema()->getComponents()[2];
    $seoTab = $tabs->getChildSchema()->getComponents()[4];

    expect($tabs)
        ->toBeInstanceOf(Tabs::class)
        ->and(str_ends_with($tabs->getKey(), 'article-editor-tabs'))
        ->toBeTrue()
        ->and($contentTab->getIcon())
        ->not()->toBeNull()
        ->and($classificationTab->isBadgeDeferred())
        ->toBeTrue()
        ->and($classificationTab->getIcon())
        ->not()->toBeNull()
        ->and($seoTab->getIcon())
        ->not()->toBeNull()
        ->and($classificationTab->getBadge())
        ->toBe(1);
});

it('spans edit form sections across the full resource content width', function () {
    $this->actingAs(filamentAdminUser());

    $tag = Tag::factory()->create();

    $page = Livewire::test(EditTag::class, ['record' => $tag->getRouteKey()])->instance();
    $schema = $page->getSchema('form');
    $section = $schema->getComponents()[0];

    expect($section->getColumnSpan('default'))
        ->toBe('full')
        ->and($section->getIcon())
        ->not()->toBeNull();
});

it('adds explicit icons to grouped infolist sections on resource detail pages', function () {
    $this->actingAs(filamentAdminUser());

    $category = Category::factory()->create();
    $feed = RssFeed::factory()->create(['category_id' => $category->id]);
    $log = RssParseLog::factory()->create(['rss_feed_id' => $feed->id]);

    $page = Livewire::test(ViewRssParseLog::class, ['record' => $log->getRouteKey()])->instance();
    $schema = $page->getSchema('infolist');
    $sections = $schema->getComponents();

    expect($sections)
        ->toHaveCount(3)
        ->and($sections[0]->getIcon())
        ->not()->toBeNull()
        ->and($sections[1]->getIcon())
        ->not()->toBeNull()
        ->and($sections[2]->getIcon())
        ->not()->toBeNull();
});
