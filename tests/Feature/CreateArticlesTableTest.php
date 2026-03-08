<?php

use Illuminate\Support\Facades\Schema;

it('creates articles table with expected columns', function () {
    expect(Schema::hasTable('articles'))->toBeTrue();

    $columns = Schema::getColumnListing('articles');

    expect($columns)->toContain(
        'id',
        'category_id',
        'sub_category_id',
        'rss_feed_id',
        'title',
        'slug',
        'source_url',
        'source_guid',
        'image_url',
        'short_description',
        'full_description',
        'rss_content',
        'author',
        'source_name',
        'status',
        'is_featured',
        'is_breaking',
        'views_count',
        'reading_time',
        'published_at',
        'rss_parsed_at',
        'created_at',
        'updated_at',
        'deleted_at',
    );
});

it('creates article indexes needed for publishing and ranking queries', function () {
    expect(Schema::hasIndex('articles', ['slug'], 'unique'))->toBeTrue()
        ->and(Schema::hasIndex('articles', ['status', 'published_at']))->toBeTrue()
        ->and(Schema::hasIndex('articles', ['category_id', 'status']))->toBeTrue()
        ->and(Schema::hasIndex('articles', ['is_pinned', 'category_id']))->toBeTrue()
        ->and(Schema::hasIndex('articles', ['importance']))->toBeTrue()
        ->and(Schema::hasIndex('articles', ['engagement_score']))->toBeTrue();
});
