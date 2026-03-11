<?php

use App\Models\Article;
use App\Models\Category;
use App\Models\SubCategory;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;

beforeEach(function () {
    $this->withoutMiddleware(VerifyCsrfToken::class);
});

it('lists subcategories through the public api filters', function () {
    $category = Category::factory()->create([
        'name' => 'Общество',
        'slug' => 'society',
    ]);
    $otherCategory = Category::factory()->create([
        'slug' => 'sport',
    ]);

    $matchingSubCategory = SubCategory::factory()->forCategory($category)->create([
        'name' => 'Жизнь',
        'slug' => 'zhizn',
        'is_active' => true,
    ]);
    SubCategory::factory()->forCategory($category)->create([
        'slug' => 'gorod',
        'is_active' => false,
    ]);
    SubCategory::factory()->forCategory($otherCategory)->create([
        'slug' => 'matchday',
        'is_active' => true,
    ]);

    Article::factory()->published()->forSubCategory($matchingSubCategory)->create();

    $this->getJson('/api/v1/categories/society/sub-categories?is_active=1&per_page=10')
        ->assertSuccessful()
        ->assertJsonPath('meta.total', 1)
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.slug', 'zhizn')
        ->assertJsonPath('data.0.category.slug', 'society')
        ->assertJsonPath('data.0.articles_count', 1);
});

it('shows a subcategory by slug through the api', function () {
    $category = Category::factory()->create([
        'slug' => 'economics',
    ]);
    $subCategory = SubCategory::factory()->forCategory($category)->create([
        'name' => 'Рынки',
        'slug' => 'rynki',
    ]);

    Article::factory()->published()->forSubCategory($subCategory)->create();

    $this->getJson('/api/v1/sub-categories/rynki')
        ->assertSuccessful()
        ->assertJsonPath('data.id', $subCategory->id)
        ->assertJsonPath('data.slug', 'rynki')
        ->assertJsonPath('data.category.slug', 'economics')
        ->assertJsonPath('data.articles_count', 1);
});

it('stores updates and deletes subcategories through the protected api routes', function () {
    $this->actingAs(filamentAdminUser());

    $category = Category::factory()->create();

    $createdResponse = $this->postJson('/api/v1/sub-categories', [
        'category_id' => $category->id,
        'name' => 'Рынок труда',
        'slug' => 'rynok-truda',
        'description' => 'Описание подрубрики.',
        'color' => '#0EA5E9',
        'icon' => '💼',
        'is_active' => true,
        'order' => 7,
    ]);

    $createdResponse->assertCreated()
        ->assertJsonPath('data.slug', 'rynok-truda')
        ->assertJsonPath('data.category.id', $category->id)
        ->assertJsonPath('data.color', '#0EA5E9');

    $subCategoryId = $createdResponse->json('data.id');

    $this->putJson('/api/v1/sub-categories/'.$subCategoryId, [
        'category_id' => $category->id,
        'name' => 'Обновлённый рынок труда',
        'slug' => 'obnovlennyi-rynok-truda',
        'description' => 'Обновлённое описание.',
        'color' => '#0284C7',
        'icon' => '📊',
        'is_active' => false,
        'order' => 9,
    ])->assertSuccessful()
        ->assertJsonPath('data.slug', 'obnovlennyi-rynok-truda')
        ->assertJsonPath('data.is_active', false);

    $this->deleteJson('/api/v1/sub-categories/'.$subCategoryId)
        ->assertNoContent();

    expect(SubCategory::query()->whereKey($subCategoryId)->exists())->toBeFalse();
});
