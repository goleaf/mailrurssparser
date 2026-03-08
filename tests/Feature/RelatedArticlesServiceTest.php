<?php

use App\Models\Article;
use App\Models\Category;
use App\Models\Tag;
use App\Services\RelatedArticlesService;
use Illuminate\Support\Facades\DB;

function createRelatedArticle(
    Category $category,
    array $attributes = [],
): Article {
    return Article::factory()->create([
        'category_id' => $category->id,
        'status' => 'published',
        'published_at' => now()->subHour(),
        'content_type' => 'analysis',
        ...$attributes,
    ]);
}

it('upserts related article rows instead of duplicating unique pairs', function () {
    $category = Category::factory()->create();
    $tag = Tag::factory()->create(['name' => 'Europe']);

    $article = createRelatedArticle($category, [
        'title' => 'Energy market outlook in europe',
        'short_description' => 'Shared energy signals for europe',
        'author' => 'Market Desk',
        'source_name' => 'РИА Новости',
    ]);
    $article->tags()->attach($tag->id);

    $candidate = createRelatedArticle($category, [
        'title' => 'Europe energy market outlook this week',
        'short_description' => 'Shared energy signals for europe this week',
        'author' => 'Market Desk',
        'source_name' => 'РИА Новости',
        'published_at' => now()->subHours(2),
    ]);
    $candidate->tags()->attach($tag->id);

    DB::table('article_related_articles')->insert([
        'article_id' => $article->id,
        'related_article_id' => $candidate->id,
        'score' => 1,
        'shared_tags_count' => 0,
        'shared_terms_count' => 0,
        'same_category' => true,
        'same_sub_category' => false,
        'same_content_type' => true,
        'same_author' => false,
        'same_source' => false,
        'created_at' => now()->subDay(),
        'updated_at' => now()->subDay(),
    ]);

    $service = new class extends RelatedArticlesService
    {
        public function forgetForArticle(Article $article): void {}
    };

    $service->rebuildForArticle($article, limit: 6);

    expect(DB::table('article_related_articles')
        ->where('article_id', $article->id)
        ->where('related_article_id', $candidate->id)
        ->count())->toBe(1)
        ->and(DB::table('article_related_articles')
            ->where('article_id', $article->id)
            ->where('related_article_id', $candidate->id)
            ->value('score'))->toBeGreaterThan(1);
});

it('returns eager loaded related and similar articles from the persisted index', function () {
    $mainCategory = Category::factory()->create();
    $otherCategory = Category::factory()->create();
    $sharedTag = Tag::factory()->create(['name' => 'AI']);

    $article = createRelatedArticle($mainCategory, [
        'title' => 'AI market outlook in europe',
        'short_description' => 'Europe market outlook and AI funding',
        'author' => 'Tech Desk',
        'source_name' => 'РИА Новости',
    ]);
    $article->tags()->attach($sharedTag->id);

    $topRelated = createRelatedArticle($mainCategory, [
        'title' => 'Europe AI market outlook this quarter',
        'short_description' => 'Europe market outlook and AI funding growth',
        'author' => 'Tech Desk',
        'source_name' => 'РИА Новости',
        'published_at' => now()->subHours(2),
    ]);
    $topRelated->tags()->attach($sharedTag->id);

    $similar = createRelatedArticle($otherCategory, [
        'title' => 'AI funding outlook in europe',
        'short_description' => 'Europe AI funding and market outlook',
        'author' => 'Tech Desk',
        'source_name' => 'РИА Новости',
        'published_at' => now()->subHours(3),
    ]);
    $similar->tags()->attach($sharedTag->id);

    createRelatedArticle($otherCategory, [
        'title' => 'Unrelated farming bulletin',
        'short_description' => 'Agriculture and weather only',
        'author' => 'Rural Desk',
        'source_name' => 'other-source.test',
        'content_type' => 'news',
        'published_at' => now()->subHours(4),
    ]);

    $service = app(RelatedArticlesService::class);
    $service->rebuildForArticle($article, limit: 10);

    $related = $service->getRelated($article, 5);
    $similarResults = $service->getSimilar($article, 5, [$topRelated->id]);

    expect($related->pluck('id'))->toContain($topRelated->id)
        ->and($related->first()?->relationLoaded('category'))->toBeTrue()
        ->and($related->first()?->relationLoaded('tags'))->toBeTrue()
        ->and($similarResults->pluck('id'))->toContain($similar->id)
        ->and($similarResults->pluck('id'))->not->toContain($topRelated->id);
});

it('returns more articles from the same category while respecting exclusions', function () {
    $category = Category::factory()->create();
    $otherCategory = Category::factory()->create();

    $article = createRelatedArticle($category, [
        'title' => 'Main category source article',
    ]);

    $included = createRelatedArticle($category, [
        'title' => 'Included same-category article',
        'published_at' => now()->subHours(2),
    ]);

    $excluded = createRelatedArticle($category, [
        'title' => 'Excluded same-category article',
        'published_at' => now()->subHours(3),
    ]);

    createRelatedArticle($otherCategory, [
        'title' => 'Other category article',
        'published_at' => now()->subHours(4),
    ]);

    $results = app(RelatedArticlesService::class)->getMoreFromCategory(
        $article,
        limit: 5,
        excludeIds: [$excluded->id],
    );

    expect($results->pluck('id'))->toContain($included->id)
        ->and($results->pluck('id'))->not->toContain($article->id)
        ->and($results->pluck('id'))->not->toContain($excluded->id)
        ->and($results->every(fn (Article $relatedArticle): bool => $relatedArticle->category_id === $category->id))->toBeTrue();
});

it('forgets direct and reverse related article rows for an article', function () {
    $category = Category::factory()->create();

    $article = createRelatedArticle($category, ['title' => 'Primary article']);
    $related = createRelatedArticle($category, ['title' => 'Secondary article']);
    $reverse = createRelatedArticle($category, ['title' => 'Reverse article']);

    DB::table('article_related_articles')->upsert([
        [
            'article_id' => $article->id,
            'related_article_id' => $related->id,
            'score' => 50,
            'shared_tags_count' => 1,
            'shared_terms_count' => 2,
            'same_category' => true,
            'same_sub_category' => false,
            'same_content_type' => true,
            'same_author' => false,
            'same_source' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ],
        [
            'article_id' => $reverse->id,
            'related_article_id' => $article->id,
            'score' => 45,
            'shared_tags_count' => 1,
            'shared_terms_count' => 1,
            'same_category' => true,
            'same_sub_category' => false,
            'same_content_type' => true,
            'same_author' => false,
            'same_source' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ],
    ], ['article_id', 'related_article_id'], [
        'score',
        'shared_tags_count',
        'shared_terms_count',
        'same_category',
        'same_sub_category',
        'same_content_type',
        'same_author',
        'same_source',
        'updated_at',
    ]);

    app(RelatedArticlesService::class)->forgetForArticle($article);

    expect(DB::table('article_related_articles')
        ->where('article_id', $article->id)
        ->orWhere('related_article_id', $article->id)
        ->exists())->toBeFalse();
});
