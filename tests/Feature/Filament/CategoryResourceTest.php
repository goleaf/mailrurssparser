<?php

use App\Filament\Resources\Categories\CategoryResource;
use App\Filament\Resources\Categories\Pages\CreateCategory;
use App\Models\Article;
use App\Models\Category;
use App\Models\RssFeed;
use App\Models\SubCategory;
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

it('loads relation counts needed for the category admin index', function () {
    $category = Category::factory()->create();

    SubCategory::factory()->count(2)->forCategory($category)->create();
    RssFeed::factory()->count(3)->forCategory($category)->create();

    Article::withoutSyncingToSearch(function () use ($category): void {
        Article::factory()->count(4)->create([
            'category_id' => $category->id,
        ]);
    });

    $record = CategoryResource::getEloquentQuery()
        ->whereKey($category->id)
        ->first();

    expect($record)
        ->not()->toBeNull()
        ->and($record?->sub_categories_count)->toBe(2)
        ->and($record?->rss_feeds_count)->toBe(3)
        ->and($record?->articles_count)->toBe(4);
});

it('creates a category with the cms form fields', function () {
    $this->actingAs(filamentAdminUser());

    Livewire::test(CreateCategory::class)
        ->fillForm([
            'name' => 'Политика',
            'slug' => 'politics',
            'color' => '#DC2626',
            'icon' => '🏛️',
            'rss_url' => rtrim((string) config('rss.feed_origin'), '/').'/rss/politics/',
            'rss_key' => 'politics',
            'description' => 'Политические новости.',
            'order' => 3,
            'is_active' => true,
            'show_in_menu' => true,
            'seo' => [
                'title' => 'Политика',
                'description' => 'Новости политики.',
                'robots' => 'index, follow',
                'image' => 'https://cdn.example.test/categories/politics.jpg',
                'canonical_url' => 'https://news.example.test/category/politics',
            ],
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
        ->and($category?->color)->toBe('#DC2626')
        ->and($category?->seo?->title)->toBe('Политика')
        ->and($category?->seo?->description)->toBe('Новости политики.')
        ->and($category?->seo?->image)->toBe('https://cdn.example.test/categories/politics.jpg')
        ->and($category?->seo?->canonical_url)->toBe('https://news.example.test/category/politics');
});
