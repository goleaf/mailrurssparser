<?php

use Illuminate\Support\Facades\Schema;

it('creates categories table with expected columns', function () {
    expect(Schema::hasTable('categories'))->toBeTrue();

    $columns = Schema::getColumnListing('categories');

    expect($columns)->toContain(
        'id',
        'name',
        'slug',
        'rss_url',
        'rss_key',
        'color',
        'icon',
        'description',
        'order',
        'is_active',
        'created_at',
        'updated_at',
    );
});
