<?php

use App\Http\Controllers\Api\ArticleController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\SearchController;
use App\Http\Controllers\Api\StatsController;
use App\Http\Controllers\Api\TagController;
use Illuminate\Support\Facades\Route;

Route::middleware('throttle:60,1')
    ->prefix('v1')
    ->name('api.')
    ->group(function (): void {
        Route::get('/articles', [ArticleController::class, 'index'])->name('articles.index');
        Route::get('/articles/featured', [ArticleController::class, 'featured'])->name('articles.featured');
        Route::get('/articles/breaking', [ArticleController::class, 'breaking'])->name('articles.breaking');
        Route::get('/articles/{slug}', [ArticleController::class, 'show'])
            ->where('slug', '[a-z0-9\-]+')
            ->name('articles.show');

        Route::get('/categories', [CategoryController::class, 'index'])->name('categories.index');
        Route::get('/categories/{slug}', [CategoryController::class, 'show'])->name('categories.show');
        Route::get('/categories/{slug}/articles', [CategoryController::class, 'articles'])->name('categories.articles');

        Route::get('/tags', [TagController::class, 'index'])->name('tags.index');
        Route::get('/tags/{slug}', [TagController::class, 'show'])->name('tags.show');
        Route::get('/tags/{slug}/articles', [TagController::class, 'articles'])->name('tags.articles');

        Route::get('/search', [SearchController::class, 'index'])->name('search');

        Route::get('/stats/overview', [StatsController::class, 'overview'])->name('stats.overview');
        Route::get('/stats/popular', [StatsController::class, 'popular'])->name('stats.popular');
        Route::get('/stats/calendar/{year}/{month}', [StatsController::class, 'calendar'])->name('stats.calendar');
        Route::get('/stats/feeds', [StatsController::class, 'feedsStatus'])->name('stats.feeds');
    });
