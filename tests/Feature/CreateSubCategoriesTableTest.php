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
        'color',
        'icon',
        'is_active',
        'order',
        'created_at',
        'updated_at',
    )
        ->and(Schema::hasIndex('sub_categories', ['slug'], 'unique'))->toBeTrue()
        ->and(Schema::hasIndex('sub_categories', ['category_id']))->toBeTrue()
        ->and(Schema::hasIndex('sub_categories', ['is_active']))->toBeTrue();
});
