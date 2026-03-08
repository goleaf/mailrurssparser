<?php

use App\Events\ArticleContentChanged;
use App\Listeners\RebuildRelatedArticlesIndex;
use App\Models\Article;
use App\Models\Category;
use App\Models\Tag;
use App\Services\ArticleCacheKey;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Events\CallQueuedListener;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;

it('queues a unique related-article rebuild listener when article content changes', function () {
    Queue::fake();
    Cache::put(ArticleCacheKey::Categories, ['stale'], 600);
    Cache::put(ArticleCacheKey::BreakingNews, ['stale'], 600);
    Cache::put(ArticleCacheKey::FeaturedArticles, ['stale'], 600);
    Cache::put(ArticleCacheKey::StatsOverview, ['stale'], 600);
    Cache::put(ArticleCacheKey::trendingTags(10), ['stale'], 600);

    $article = Article::factory()->create([
        'status' => 'published',
        'published_at' => now()->subHour(),
    ]);

    Queue::assertPushed(CallQueuedListener::class, function (CallQueuedListener $job) use ($article): bool {
        return $job->class === RebuildRelatedArticlesIndex::class
            && ($job->data[0]->articleId ?? null) === $article->id;
    });

    expect(Cache::missing(ArticleCacheKey::Categories))->toBeTrue()
        ->and(Cache::missing(ArticleCacheKey::BreakingNews))->toBeTrue()
        ->and(Cache::missing(ArticleCacheKey::FeaturedArticles))->toBeTrue()
        ->and(Cache::missing(ArticleCacheKey::StatsOverview))->toBeTrue()
        ->and(Cache::missing(ArticleCacheKey::trendingTags(10)))->toBeTrue();
});

it('rebuilds related article rows through the listener', function () {
    $category = Category::factory()->create();
    $tag = Tag::factory()->create();

    $article = Article::factory()->create([
        'category_id' => $category->id,
        'title' => 'Central bank outlook for europe',
        'short_description' => 'Inflation and rates outlook',
        'status' => 'published',
        'published_at' => now()->subHour(),
    ]);
    $article->tags()->attach($tag->id);

    $related = Article::factory()->create([
        'category_id' => $category->id,
        'title' => 'Europe rates outlook this week',
        'short_description' => 'Central bank rates focus',
        'status' => 'published',
        'published_at' => now()->subHours(2),
    ]);
    $related->tags()->attach($tag->id);

    $listener = app(RebuildRelatedArticlesIndex::class);

    expect($listener)->toBeInstanceOf(ShouldQueue::class)
        ->and($listener)->toBeInstanceOf(ShouldBeUnique::class)
        ->and($listener->uniqueId(new ArticleContentChanged($article->id)))
        ->toBe('article-related-sync:'.$article->id);

    $listener->handle(new ArticleContentChanged($article->id));

    expect(
        DB::table('article_related_articles')
            ->where('article_id', $article->id)
            ->where('related_article_id', $related->id)
            ->exists(),
    )->toBeTrue();
});
