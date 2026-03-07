<?php

namespace App\Observers;

use App\Models\Article;
use Illuminate\Support\Facades\Cache;

class ArticleObserver
{
    public function created(Article $article): void
    {
        $this->forgetCaches();
    }

    public function updated(Article $article): void
    {
        $this->forgetCaches();
    }

    public function deleted(Article $article): void
    {
        $this->forgetCaches();
    }

    public function restored(Article $article): void
    {
        $this->forgetCaches();
    }

    public function forceDeleted(Article $article): void
    {
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
