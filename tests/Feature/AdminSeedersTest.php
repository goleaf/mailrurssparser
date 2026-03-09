<?php

use App\Models\Article;
use App\Models\ArticleView;
use App\Models\Bookmark;
use App\Models\Category;
use App\Models\Metric;
use App\Models\NewsletterSubscriber;
use App\Models\RssFeed;
use App\Models\RssParseLog;
use App\Models\Tag;
use App\Models\User;

it('builds a relation-rich admin dataset through the database seeder', function () {
    $this->seed();

    $firstCategory = Category::query()
        ->withCount(['subCategories', 'articles', 'rssFeeds'])
        ->orderBy('id')
        ->first();

    expect(User::query()->where('email', 'admin@example.com')->exists())->toBeTrue()
        ->and(Tag::query()->count())->toBeGreaterThanOrEqual(20)
        ->and(RssFeed::query()->count())->toBeGreaterThanOrEqual(20)
        ->and(NewsletterSubscriber::query()->count())->toBeGreaterThanOrEqual(30)
        ->and(Article::query()->count())->toBeGreaterThanOrEqual(20)
        ->and(ArticleView::query()->count())->toBeGreaterThanOrEqual(20)
        ->and(Bookmark::query()->count())->toBeGreaterThanOrEqual(20)
        ->and(RssParseLog::query()->count())->toBeGreaterThanOrEqual(20)
        ->and(Metric::query()->count())->toBeGreaterThan(0)
        ->and($firstCategory)->not()->toBeNull()
        ->and($firstCategory?->sub_categories_count)->toBeGreaterThanOrEqual(20)
        ->and($firstCategory?->articles_count)->toBeGreaterThanOrEqual(20)
        ->and($firstCategory?->articles_count_cache)->toBeGreaterThanOrEqual(20);
});
