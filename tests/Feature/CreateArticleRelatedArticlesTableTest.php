<?php

use Illuminate\Support\Facades\Schema;

it('creates article related articles table with expected columns', function () {
    expect(Schema::hasTable('article_related_articles'))->toBeTrue();

    $columns = Schema::getColumnListing('article_related_articles');

    expect($columns)->toContain(
        'article_id',
        'related_article_id',
        'score',
        'shared_tags_count',
        'shared_terms_count',
        'same_category',
        'same_sub_category',
        'same_content_type',
        'same_author',
        'same_source',
        'created_at',
        'updated_at',
    );
});
