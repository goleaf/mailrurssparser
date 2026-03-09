<?php

use App\Filament\Resources\Articles\ArticleResource;
use App\Filament\Resources\Articles\Pages\EditArticle;
use App\Filament\Resources\Articles\Pages\ListArticles;
use App\Filament\Resources\Categories\Pages\ListCategories;
use App\Filament\Resources\RssFeeds\Pages\ListRssFeeds;
use App\Filament\Resources\Tags\Pages\ListTags;
use App\Filament\Support\AdminNavigationGroup;
use App\Models\Article;
use App\Models\Tag;
use App\Models\User;
use App\Providers\Filament\AdminPanelProvider;
use App\Services\ArticleStatus;
use Filament\Facades\Filament;
use Filament\Panel;
use Filament\Schemas\Components\Tabs;
use Filament\Tables\Enums\ColumnManagerLayout;
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

it('enables the v5.3 column manager on cms list pages', function (string $pageClass) {
    $this->actingAs(User::factory()->create());

    $table = Livewire::test($pageClass)->instance()->getTable();

    expect($table->hasColumnManager())
        ->toBeTrue()
        ->and($table->hasReorderableColumns())
        ->toBeTrue()
        ->and($table->getColumnManagerLayout())
        ->toBe(ColumnManagerLayout::Modal)
        ->and($table->getColumnManagerColumns())
        ->toBe(2);
})->with('cms_list_pages');

it('uses a slide-over column manager on the article table', function () {
    $this->actingAs(User::factory()->create());

    $table = Livewire::test(ListArticles::class)->instance()->getTable();

    expect($table->getColumnManagerTriggerAction()->isModalSlideOver())
        ->toBeTrue()
        ->and($table->getColumn('is_featured')->isToggleable())
        ->toBeTrue()
        ->and($table->getColumn('views_count')->isToggledHiddenByDefault())
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
