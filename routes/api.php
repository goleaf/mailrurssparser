<?php

use App\Http\Controllers\Api\ArticleController;
use App\Http\Controllers\Api\BookmarkController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\NewsletterController;
use App\Http\Controllers\Api\RssApiController;
use App\Http\Controllers\Api\SearchController;
use App\Http\Controllers\Api\ShareController;
use App\Http\Controllers\Api\StatsController;
use App\Http\Controllers\Api\SubCategoryController;
use App\Http\Controllers\Api\TagController;
use App\Services\ApiRateLimiter;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Route;

Route::middleware(['api.context', ThrottleRequests::using(ApiRateLimiter::Api)])
    ->prefix('v1')
    ->name('api.v1.')
    ->group(function (): void {
        Route::get('articles', [ArticleController::class, 'index'])->name('articles.index');
        Route::get('articles/featured', [ArticleController::class, 'featured'])->name('articles.featured');
        Route::get('articles/breaking', [ArticleController::class, 'breaking'])->name('articles.breaking');
        Route::get('articles/trending', [ArticleController::class, 'trending'])->name('articles.trending');
        Route::get('articles/{slug}', [ArticleController::class, 'show'])->where('slug', '[a-z0-9\-]+')->name('articles.show');
        Route::get('articles/{slug}/related', [ArticleController::class, 'related'])->name('articles.related');
        Route::get('articles/{slug}/similar', [ArticleController::class, 'similar'])->name('articles.similar');
        Route::get('category/{slug}/pinned', [ArticleController::class, 'pinned'])->name('articles.pinned');

        Route::get('bookmarks', [BookmarkController::class, 'index'])->name('bookmarks.index');
        Route::post('bookmarks/check', [BookmarkController::class, 'check'])->name('bookmarks.check');
        Route::post('bookmarks/{articleId}', [BookmarkController::class, 'toggle'])->name('bookmarks.toggle');

        Route::post('share/{articleId}', [ShareController::class, 'track'])->name('share.track');

        Route::get('categories', [CategoryController::class, 'index'])->name('categories.index');
        Route::get('categories/{slug}', [CategoryController::class, 'show'])->name('categories.show');
        Route::get('categories/{slug}/articles', [CategoryController::class, 'articles'])->name('categories.articles');
        Route::get('categories/{slug}/sub-categories', [SubCategoryController::class, 'index'])->name('categories.sub-categories.index');

        Route::get('sub-categories', [SubCategoryController::class, 'index'])->name('sub-categories.index');
        Route::get('sub-categories/{identifier}', [SubCategoryController::class, 'show'])->name('sub-categories.show');
        Route::middleware(['web', 'auth'])->group(function (): void {
            Route::post('sub-categories', [SubCategoryController::class, 'store'])->name('sub-categories.store');
            Route::put('sub-categories/{identifier}', [SubCategoryController::class, 'update'])->name('sub-categories.update');
            Route::delete('sub-categories/{identifier}', [SubCategoryController::class, 'destroy'])->name('sub-categories.destroy');
        });

        Route::get('tags', [TagController::class, 'index'])->name('tags.index');
        Route::get('tags/trending', [TagController::class, 'trending'])->name('tags.trending');
        Route::get('tags/{slug}', [TagController::class, 'show'])->name('tags.show');
        Route::get('tags/{slug}/articles', [TagController::class, 'articles'])->name('tags.articles');

        Route::get('search', [SearchController::class, 'index'])->middleware(ThrottleRequests::using(ApiRateLimiter::Search))->name('search.index');
        Route::get('search/suggest', [SearchController::class, 'suggest'])->middleware(ThrottleRequests::using(ApiRateLimiter::SearchSuggest))->name('search.suggest');
        Route::get('search/highlights', [SearchController::class, 'highlights'])->name('search.highlights');

        Route::get('stats/overview', [StatsController::class, 'overview'])->name('stats.overview');
        Route::get('stats/metrics', [StatsController::class, 'metrics'])->name('stats.metrics');
        Route::get('stats/chart', [StatsController::class, 'chart'])->name('stats.chart');
        Route::get('stats/popular', [StatsController::class, 'popular'])->name('stats.popular');
        Route::get('stats/calendar/{year}/{month}', [StatsController::class, 'calendar'])->name('stats.calendar');
        Route::get('stats/feeds', [StatsController::class, 'feedsPerformance'])->name('stats.feeds');
        Route::get('stats/categories', [StatsController::class, 'categoryBreakdown'])->name('stats.categories');

        Route::prefix('rss')
            ->middleware(ThrottleRequests::using(ApiRateLimiter::Rss))
            ->group(function (): void {
                Route::get('status', [RssApiController::class, 'status'])->name('rss.status');
                Route::post('parse', [RssApiController::class, 'parseAll'])->name('rss.parse-all');
                Route::post('parse/{feedId}', [RssApiController::class, 'parseFeed'])->name('rss.parse-feed');
                Route::post('parse/category/{slug}', [RssApiController::class, 'parseCategory'])->name('rss.parse-category');
            });

        Route::post('newsletter/subscribe', [NewsletterController::class, 'subscribe'])->name('newsletter.subscribe');
        Route::get('newsletter/confirm/{token}', [NewsletterController::class, 'confirm'])->name('newsletter.confirm');
        Route::get('newsletter/unsubscribe/{token}', [NewsletterController::class, 'unsubscribe'])->name('newsletter.unsubscribe');
    });
