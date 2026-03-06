<?php

use App\Filament\Resources\Categories\CategoryResource;
use App\Models\Article;
use App\Models\Category;

it('loads articles_count in the resource query', function () {
    $category = Category::factory()->create();

    Article::withoutSyncingToSearch(function () use ($category): void {
        Article::factory()
            ->count(2)
            ->create([
                'category_id' => $category->id,
            ]);
    });

    $record = CategoryResource::getEloquentQuery()
        ->whereKey($category->id)
        ->first();

    expect($record)
        ->not()->toBeNull()
        ->and($record->articles_count)->toBe(2);
});
