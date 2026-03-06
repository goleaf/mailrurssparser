<?php

use Illuminate\Support\Facades\Route;

it('registers api routes', function () {
    expect(Route::has('api.articles.index'))->toBeTrue()
        ->and(Route::has('api.articles.featured'))->toBeTrue()
        ->and(Route::has('api.articles.breaking'))->toBeTrue()
        ->and(Route::has('api.articles.show'))->toBeTrue()
        ->and(Route::has('api.categories.index'))->toBeTrue()
        ->and(Route::has('api.categories.show'))->toBeTrue()
        ->and(Route::has('api.categories.articles'))->toBeTrue()
        ->and(Route::has('api.tags.index'))->toBeTrue()
        ->and(Route::has('api.tags.show'))->toBeTrue()
        ->and(Route::has('api.tags.articles'))->toBeTrue()
        ->and(Route::has('api.search'))->toBeTrue()
        ->and(Route::has('api.stats.overview'))->toBeTrue()
        ->and(Route::has('api.stats.popular'))->toBeTrue()
        ->and(Route::has('api.stats.calendar'))->toBeTrue()
        ->and(Route::has('api.stats.feeds'))->toBeTrue();
});
