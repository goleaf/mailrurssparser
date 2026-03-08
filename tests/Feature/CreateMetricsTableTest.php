<?php

use Illuminate\Support\Facades\Schema;

it('creates metrics table with the expected columns', function () {
    expect(Schema::hasTable('metrics'))->toBeTrue();

    $columns = Schema::getColumnListing('metrics');

    expect($columns)->toContain(
        'id',
        'name',
        'category',
        'measurable_type',
        'measurable_id',
        'bucket_start',
        'bucket_date',
        'fingerprint',
        'value',
        'created_at',
        'updated_at',
    );
});

it('creates metric indexes for rollups and model lookups', function () {
    expect(Schema::hasIndex('metrics', ['fingerprint'], 'unique'))->toBeTrue()
        ->and(Schema::hasIndex('metrics', ['name', 'bucket_start']))->toBeTrue()
        ->and(Schema::hasIndex('metrics', ['category', 'bucket_start']))->toBeTrue()
        ->and(Schema::hasIndex('metrics', ['measurable_type', 'measurable_id', 'name']))->toBeTrue()
        ->and(Schema::hasIndex('metrics', ['bucket_date', 'name']))->toBeTrue();
});
