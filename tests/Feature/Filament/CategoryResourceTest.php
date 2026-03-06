<?php

use App\Filament\Resources\Categories\CategoryResource;
use App\Filament\Resources\Categories\Pages\ListCategories;
use App\Models\Article;
use App\Models\Category;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Livewire\Livewire;

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

it('only shows the delete action when there are no articles', function () {
    $this->actingAs(User::factory()->create());

    $categoryWithArticles = Category::factory()->create();
    $categoryWithoutArticles = Category::factory()->create();

    Article::withoutSyncingToSearch(function () use ($categoryWithArticles): void {
        Article::factory()->create([
            'category_id' => $categoryWithArticles->id,
        ]);
    });

    Livewire::test(ListCategories::class)
        ->assertActionHidden(TestAction::make('delete')->table($categoryWithArticles))
        ->assertActionVisible(TestAction::make('delete')->table($categoryWithoutArticles));
});
