<?php

use Illuminate\Support\Facades\Schema;

it('creates rss feeds table with expected columns', function () {
    expect(Schema::hasTable('rss_feeds'))->toBeTrue();

    $columns = Schema::getColumnListing('rss_feeds');

    expect($columns)->toContain(
        'id',
        'category_id',
        'title',
        'url',
        'is_active',
        'last_parsed_at',
        'articles_parsed_total',
        'last_run_new_count',
        'last_run_skip_count',
        'last_error',
        'created_at',
        'updated_at',
    );
});

it('creates rss feed indexes for scheduling lookups', function () {
    expect(Schema::hasIndex('rss_feeds', ['url'], 'unique'))->toBeTrue()
        ->and(Schema::hasIndex('rss_feeds', ['is_active', 'next_parse_at']))->toBeTrue();
});
