<?php

namespace App\Observers;

use App\Models\Article;
use App\Services\RelatedArticlesService;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;
use Illuminate\Support\Facades\Cache;

class ArticleObserver implements ShouldHandleEventsAfterCommit
{
    public function created(Article $article): void
    {
        $this->resetContentIndexes($article);
    }

    public function updated(Article $article): void
    {
        $this->resetContentIndexes($article);
    }

    public function deleted(Article $article): void
    {
        $this->resetContentIndexes($article);
    }

    public function restored(Article $article): void
    {
        $this->resetContentIndexes($article);
    }

    public function forceDeleted(Article $article): void
    {
        $this->resetContentIndexes($article);
    }

    private function resetContentIndexes(Article $article): void
    {
        app(RelatedArticlesService::class)->forgetForArticle($article);
        $this->forgetCaches();
    }

    private function forgetCaches(): void
    {
        Cache::forget('categories');
        Cache::forget('breaking_news');
        Cache::forget('featured_articles');
        Cache::forget('stats_overview');

        foreach ([10, 20, 30, 50, 100] as $limit) {
            Cache::forget("trending_tags_{$limit}");
        }
    }
}
