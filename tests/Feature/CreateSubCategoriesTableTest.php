<?php

use Illuminate\Support\Facades\Schema;

it('creates sub categories table with expected columns', function () {
    expect(Schema::hasTable('sub_categories'))->toBeTrue();

    $columns = Schema::getColumnListing('sub_categories');

    expect($columns)->toContain(
        'id',
        'category_id',
        'name',
        'slug',
        'description',
        'is_active',
        'created_at',
        'updated_at',
    );
});
