<?php

use Illuminate\Support\Facades\Schema;

it('creates tags table with expected columns', function () {
    expect(Schema::hasTable('tags'))->toBeTrue();

    $columns = Schema::getColumnListing('tags');

    expect($columns)->toContain(
        'id',
        'name',
        'slug',
        'color',
        'usage_count',
        'created_at',
        'updated_at',
    );
});
