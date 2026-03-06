<?php

use Illuminate\Support\Facades\Schema;

it('creates article tag table with expected columns', function () {
    expect(Schema::hasTable('article_tag'))->toBeTrue();

    $columns = Schema::getColumnListing('article_tag');

    expect($columns)->toContain(
        'article_id',
        'tag_id',
    );
});
