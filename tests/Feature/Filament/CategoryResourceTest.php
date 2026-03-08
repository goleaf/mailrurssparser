<?php

use App\Filament\Resources\Categories\CategoryResource;
use App\Filament\Resources\Categories\Pages\CreateCategory;
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

it('creates a category with the cms form fields', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test(CreateCategory::class)
        ->fillForm([
            'name' => 'Политика',
            'slug' => 'politics',
            'color' => '#DC2626',
            'icon' => '🏛️',
            'rss_url' => rtrim((string) config('rss.feed_origin'), '/').'/rss/politics/',
            'rss_key' => 'politics',
            'description' => 'Политические новости.',
            'meta_title' => 'Политика',
            'meta_description' => 'Новости политики.',
            'order' => 3,
            'is_active' => true,
            'show_in_menu' => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors()
        ->assertNotified()
        ->assertRedirect();

    $category = Category::query()->where('slug', 'politics')->first();

    expect($category)
        ->not()->toBeNull()
        ->and($category?->rss_key)->toBe('politics')
        ->and($category?->show_in_menu)->toBeTrue()
        ->and($category?->color)->toBe('#DC2626');
});
