<?php

use Illuminate\Support\Facades\Artisan;

it('lists the configured rss and maintenance schedule entries', function () {
    $exitCode = Artisan::call('schedule:list');
    $output = Artisan::output();

    expect($exitCode)->toBe(0);
    expect($output)
        ->toContain('php artisan rss:parse --due')
        ->toContain('php artisan rss:parse --category=main')
        ->toContain('php artisan rss:clean --days=90 --force')
        ->toContain('php artisan rss:reindex')
        ->toContain('recalculate-engagement')
        ->toContain('update-tag-counts')
        ->toContain('update-category-counts')
        ->toContain('expire-breaking-news');
});
