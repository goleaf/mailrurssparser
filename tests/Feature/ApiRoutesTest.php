<?php

use Illuminate\Support\Facades\Route;

it('registers api routes', function () {
    expect(Route::has('api.v1.articles.index'))->toBeTrue()
        ->and(Route::has('api.v1.articles.featured'))->toBeTrue()
        ->and(Route::has('api.v1.articles.breaking'))->toBeTrue()
        ->and(Route::has('api.v1.articles.trending'))->toBeTrue()
        ->and(Route::has('api.v1.articles.show'))->toBeTrue()
        ->and(Route::has('api.v1.articles.related'))->toBeTrue()
        ->and(Route::has('api.v1.articles.similar'))->toBeTrue()
        ->and(Route::has('api.v1.articles.pinned'))->toBeTrue()
        ->and(Route::has('api.v1.bookmarks.index'))->toBeTrue()
        ->and(Route::has('api.v1.bookmarks.check'))->toBeTrue()
        ->and(Route::has('api.v1.bookmarks.toggle'))->toBeTrue()
        ->and(Route::has('api.v1.share.track'))->toBeTrue()
        ->and(Route::has('api.v1.categories.index'))->toBeTrue()
        ->and(Route::has('api.v1.categories.show'))->toBeTrue()
        ->and(Route::has('api.v1.categories.articles'))->toBeTrue()
        ->and(Route::has('api.v1.tags.index'))->toBeTrue()
        ->and(Route::has('api.v1.tags.trending'))->toBeTrue()
        ->and(Route::has('api.v1.tags.show'))->toBeTrue()
        ->and(Route::has('api.v1.tags.articles'))->toBeTrue()
        ->and(Route::has('api.v1.search.index'))->toBeTrue()
        ->and(Route::has('api.v1.search.suggest'))->toBeTrue()
        ->and(Route::has('api.v1.search.highlights'))->toBeTrue()
        ->and(Route::has('api.v1.stats.overview'))->toBeTrue()
        ->and(Route::has('api.v1.stats.metrics'))->toBeTrue()
        ->and(Route::has('api.v1.stats.chart'))->toBeTrue()
        ->and(Route::has('api.v1.stats.popular'))->toBeTrue()
        ->and(Route::has('api.v1.stats.calendar'))->toBeTrue()
        ->and(Route::has('api.v1.stats.feeds'))->toBeTrue()
        ->and(Route::has('api.v1.stats.categories'))->toBeTrue()
        ->and(Route::has('api.v1.rss.status'))->toBeTrue()
        ->and(Route::has('api.v1.rss.parse-all'))->toBeTrue()
        ->and(Route::has('api.v1.rss.parse-feed'))->toBeTrue()
        ->and(Route::has('api.v1.rss.parse-category'))->toBeTrue()
        ->and(Route::has('api.v1.newsletter.subscribe'))->toBeTrue()
        ->and(Route::has('api.v1.newsletter.confirm'))->toBeTrue()
        ->and(Route::has('api.v1.newsletter.unsubscribe'))->toBeTrue();
});

it('registers seo and spa web routes', function () {
    expect(Route::has('home'))->toBeTrue()
        ->and(Route::has('sitemap'))->toBeTrue()
        ->and(Route::has('rss-feed'))->toBeTrue()
        ->and(Route::has('robots'))->toBeTrue()
        ->and(Route::has('offline'))->toBeTrue()
        ->and(Route::has('spa'))->toBeTrue();
});
