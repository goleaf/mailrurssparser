<?php

use App\Filament\Pages\ManageRssFeeds;
use App\Filament\Pages\ParseHistory;
use App\Filament\Resources\Articles\ArticleResource;
use App\Filament\Resources\Articles\Pages\EditArticle;
use App\Filament\Resources\Articles\Pages\ListArticles;
use App\Filament\Resources\ArticleViews\ArticleViewResource;
use App\Filament\Resources\Bookmarks\BookmarkResource;
use App\Filament\Resources\Categories\CategoryResource;
use App\Filament\Resources\Categories\Pages\ListCategories;
use App\Filament\Resources\Metrics\MetricResource;
use App\Filament\Resources\NewsletterSubscribers\NewsletterSubscriberResource;
use App\Filament\Resources\RssFeeds\Pages\ListRssFeeds;
use App\Filament\Resources\RssFeeds\RssFeedResource;
use App\Filament\Resources\RssParseLogs\RssParseLogResource;
use App\Filament\Resources\SubCategories\SubCategoryResource;
use App\Filament\Resources\Tags\Pages\ListTags;
use App\Filament\Resources\Tags\TagResource;
use App\Filament\Support\AdminNavigationGroup;
use App\Models\Article;
use App\Models\Tag;
use App\Models\User;
use App\Providers\Filament\AdminPanelProvider;
use App\Services\ArticleStatus;
use Filament\Facades\Filament;
use Filament\Panel;
use Filament\Schemas\Components\Tabs;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
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

it('keeps admin navigation groups iconized and labeled through the enum', function () {
    expect(class_implements(AdminNavigationGroup::class))
        ->toHaveKey(HasLabel::class)
        ->toHaveKey(HasIcon::class)
        ->and(collect(AdminNavigationGroup::cases())->every(
            fn (AdminNavigationGroup $group): bool => filled($group->getIcon()),
        ))
        ->toBeTrue();
});

it('removes item icons when the navigation group already provides the icon', function (
    string $navigationItemClass,
    AdminNavigationGroup $navigationGroup,
) {
    expect($navigationItemClass::getNavigationGroup())
        ->toBe($navigationGroup)
        ->and($navigationGroup->getIcon())
        ->not()->toBeNull()
        ->and($navigationItemClass::getNavigationIcon())
        ->toBeNull();
})->with('grouped_navigation_items');

it('renders the admin dashboard without group and item icon conflicts', function () {
    $this->actingAs(User::factory()->create(['email_verified_at' => now()]));

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
    $this->actingAs(User::factory()->create());

    $table = Livewire::test($pageClass)->instance()->getTable();

    expect($table->hasColumnManager())
        ->toBeFalse();
})->with('cms_list_pages');

it('keeps the dense article table configurable through toggleable columns instead of modal tools', function () {
    $this->actingAs(User::factory()->create());

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

it('marks the article classification tab badge as deferred for existing records', function () {
    $this->actingAs(User::factory()->create());

    $tag = Tag::factory()->create();

    $article = Article::withoutSyncingToSearch(function () use ($tag): Article {
        $article = Article::factory()->create();
        $article->tags()->sync([$tag->id]);

        return $article;
    });

    $page = Livewire::test(EditArticle::class, ['record' => $article->getRouteKey()])->instance();
    $schema = $page->getSchema('form');

    $tabs = $schema->getComponents()[0];
    $classificationTab = $tabs->getChildSchema()->getComponents()[2];

    expect($tabs)
        ->toBeInstanceOf(Tabs::class)
        ->and(str_ends_with($tabs->getKey(), 'article-editor-tabs'))
        ->toBeTrue()
        ->and($classificationTab->isBadgeDeferred())
        ->toBeTrue()
        ->and($classificationTab->getBadge())
        ->toBe(1);
});
