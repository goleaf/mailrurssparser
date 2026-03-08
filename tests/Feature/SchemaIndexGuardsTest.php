<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

afterEach(function () {
    Schema::dropIfExists('schema_index_guards');
});

it('runs schema index callbacks only when the target index state matches', function () {
    Schema::create('schema_index_guards', function (Blueprint $table) {
        $table->id();
        $table->string('status')->nullable();
    });

    $created = false;

    Schema::whenTableDoesntHaveIndex('schema_index_guards', ['status'], function () use (&$created): void {
        $created = true;

        Schema::table('schema_index_guards', function (Blueprint $table) {
            $table->index('status');
        });
    });

    $skippedCreate = false;

    Schema::whenTableDoesntHaveIndex('schema_index_guards', ['status'], function () use (&$skippedCreate): void {
        $skippedCreate = true;
    });

    $dropped = false;

    Schema::whenTableHasIndex('schema_index_guards', ['status'], function () use (&$dropped): void {
        $dropped = true;

        Schema::table('schema_index_guards', function (Blueprint $table) {
            $table->dropIndex(['status']);
        });
    });

    $skippedDrop = false;

    Schema::whenTableHasIndex('schema_index_guards', ['status'], function () use (&$skippedDrop): void {
        $skippedDrop = true;
    });

    expect($created)->toBeTrue()
        ->and($skippedCreate)->toBeFalse()
        ->and($dropped)->toBeTrue()
        ->and($skippedDrop)->toBeFalse()
        ->and(Schema::hasIndex('schema_index_guards', ['status']))->toBeFalse();
});
