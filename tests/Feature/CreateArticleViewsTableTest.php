<?php

use Illuminate\Support\Facades\Schema;

it('creates article views table with expected columns', function () {
    expect(Schema::hasTable('article_views'))->toBeTrue();

    $columns = Schema::getColumnListing('article_views');

    expect($columns)->toContain(
        'id',
        'article_id',
        'ip_address',
        'session_id',
        'user_agent',
        'referer',
        'viewed_at',
    );
});
