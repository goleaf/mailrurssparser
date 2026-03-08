<?php

namespace App\Observers;

use App\Events\ArticleContentChanged;
use App\Models\Article;
use App\Services\ArticleCacheKey;
use App\Services\RelatedArticlesService;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;
use Illuminate\Support\Facades\Cache;

class ArticleObserver implements ShouldHandleEventsAfterCommit
{
    public function created(Article $article): void
    {
        $this->resetContentIndexes($article, dispatchSync: true);
    }

    public function updated(Article $article): void
    {
        $this->resetContentIndexes($article, dispatchSync: true);
    }

    public function deleted(Article $article): void
    {
        $this->resetContentIndexes($article);
    }

    public function restored(Article $article): void
    {
        $this->resetContentIndexes($article, dispatchSync: true);
    }

    public function forceDeleted(Article $article): void
    {
        $this->resetContentIndexes($article);
    }

    private function resetContentIndexes(Article $article, bool $dispatchSync = false): void
    {
        app(RelatedArticlesService::class)->forgetForArticle($article);
        $this->forgetCaches();

        if ($dispatchSync) {
            event(new ArticleContentChanged($article->getKey()));
        }
    }

    private function forgetCaches(): void
    {
        Cache::forget(ArticleCacheKey::Categories);
        Cache::forget(ArticleCacheKey::BreakingNews);
        Cache::forget(ArticleCacheKey::FeaturedArticles);
        Cache::forget(ArticleCacheKey::StatsOverview);

        foreach ([10, 20, 30, 50, 100] as $limit) {
            Cache::forget(ArticleCacheKey::trendingTags($limit));
        }
    }
}
