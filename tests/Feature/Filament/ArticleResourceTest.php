<?php

use App\Filament\Resources\Articles\ArticleResource;
use App\Filament\Resources\Articles\Pages\CreateArticle;
use App\Filament\Resources\Articles\Pages\ListArticles;
use App\Models\Article;
use App\Models\Category;
use App\Models\RssFeed;
use App\Models\SubCategory;
use App\Models\Tag;
use App\Models\User;
use App\Services\ArticleContentType;
use App\Services\ArticleStatus;
use Filament\Actions\Testing\TestAction;
use Livewire\Livewire;

afterEach(function () {
    \Mockery::close();
});

it('publishes selected articles from the bulk action', function () {
    $this->actingAs(filamentAdminUser());

    $articles = Article::withoutSyncingToSearch(function () {
        return Article::factory()
            ->count(2)
            ->create(['status' => 'draft']);
    });

    Article::withoutSyncingToSearch(function () use ($articles): void {
        Livewire::test(ListArticles::class)
            ->selectTableRecords($articles->pluck('id')->all())
            ->callAction(TestAction::make('publishSelected')->table()->bulk());
    });

    $articles->each(function (Article $article): void {
        expect($article->refresh()->status)->toBe(ArticleStatus::Published);
    });
});

it('uses the full create page instead of an inline import modal', function () {
    $this->actingAs(filamentAdminUser());

    Livewire::test(ListArticles::class)
        ->assertDontSee('Импорт из ссылки')
        ->assertSee('Новая статья');
});

it('eager loads the article cms relations in the resource query', function () {
    $category = Category::factory()->create();
    $subCategory = SubCategory::factory()->create([
        'category_id' => $category->id,
    ]);
    $feed = RssFeed::factory()->create([
        'category_id' => $category->id,
    ]);
    $tag = Tag::factory()->create();

    $article = Article::withoutSyncingToSearch(function () use ($category, $subCategory, $feed, $tag): Article {
        $article = Article::factory()->create([
            'category_id' => $category->id,
            'sub_category_id' => $subCategory->id,
            'rss_feed_id' => $feed->id,
            'editor_id' => User::factory()->create()->id,
        ]);
        $article->tags()->sync([$tag->id]);

        return $article;
    });

    $record = ArticleResource::getEloquentQuery()
        ->whereKey($article->id)
        ->first();

    expect($record)
        ->not()->toBeNull()
        ->and($record?->relationLoaded('category'))->toBeTrue()
        ->and($record?->relationLoaded('subCategory'))->toBeTrue()
        ->and($record?->relationLoaded('tags'))->toBeTrue()
        ->and($record?->relationLoaded('rssFeed'))->toBeTrue()
        ->and($record?->relationLoaded('editor'))->toBeTrue()
        ->and($record?->views_count)->toBeInt()
        ->and($record?->bookmarked_by_count)->toBeInt()
        ->and($record?->related_articles_count)->toBeInt();
});

it('creates an article through the cms form tabs', function () {
    $this->actingAs(filamentAdminUser());

    $category = Category::factory()->create();
    $subCategory = SubCategory::factory()->create([
        'category_id' => $category->id,
    ]);
    $feed = RssFeed::factory()->create([
        'category_id' => $category->id,
    ]);
    $tag = Tag::factory()->create();
    $editor = User::factory()->create();

    Article::withoutSyncingToSearch(function () use ($category, $subCategory, $feed, $tag, $editor): void {
        Livewire::test(CreateArticle::class)
            ->fillForm([
                'title' => 'CMS managed article',
                'slug' => 'cms-managed-article',
                'category_id' => $category->id,
                'sub_category_id' => $subCategory->id,
                'rss_feed_id' => $feed->id,
                'editor_id' => $editor->id,
                'short_description' => 'Краткое описание статьи для карточки.',
                'full_description' => '<p>Полное содержание статьи.</p>',
                'source_name' => '',
                'content_type' => 'analysis',
                'importance' => 8,
                'tags' => [$tag->id],
                'status' => 'draft',
            ])
            ->call('create')
            ->assertHasNoFormErrors()
            ->assertNotified()
            ->assertRedirect();
    });

    $article = Article::query()
        ->where('slug', 'cms-managed-article')
        ->first();

    expect($article)
        ->not()->toBeNull()
        ->and($article?->category_id)->toBe($category->id)
        ->and($article?->sub_category_id)->toBe($subCategory->id)
        ->and($article?->rss_feed_id)->toBe($feed->id)
        ->and($article?->editor_id)->toBe($editor->id)
        ->and($article?->content_type)->toBe(ArticleContentType::Analysis)
        ->and($article?->importance)->toBe(8)
        ->and($article?->tags()->pluck('tags.id')->all())->toBe([$tag->id]);
});
