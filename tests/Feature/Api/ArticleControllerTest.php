<?php

use App\Models\Article;
use App\Models\Category;
use App\Models\Tag;

beforeEach(function () {
    if (! trait_exists(Laravel\Scout\Searchable::class)) {
        $this->markTestSkipped('Laravel Scout is not installed.');
    }
});

it('filters the index by category, tags, content type, importance, and excludes ids', function () {
    $mainCategory = Category::factory()->create(['slug' => 'main']);
    $otherCategory = Category::factory()->create(['slug' => 'sport']);
    $sharedTag = Tag::factory()->create(['slug' => 'shared']);
    $otherTag = Tag::factory()->create(['slug' => 'other']);

    $matchingArticle = Article::factory()->create([
        'category_id' => $mainCategory->id,
        'title' => 'Main feature story',
        'content_type' => 'analysis',
        'importance' => 9,
        'status' => 'published',
        'published_at' => now()->subHour(),
    ]);
    $matchingArticle->tags()->attach($sharedTag->id);

    $excludedArticle = Article::factory()->create([
        'category_id' => $mainCategory->id,
        'title' => 'Excluded story',
        'content_type' => 'analysis',
        'importance' => 9,
        'status' => 'published',
        'published_at' => now()->subHours(2),
    ]);
    $excludedArticle->tags()->attach($sharedTag->id);

    $wrongTagArticle = Article::factory()->create([
        'category_id' => $mainCategory->id,
        'title' => 'Wrong tag story',
        'content_type' => 'analysis',
        'importance' => 9,
        'status' => 'published',
        'published_at' => now()->subHours(3),
    ]);
    $wrongTagArticle->tags()->attach($otherTag->id);

    Article::factory()->create([
        'category_id' => $otherCategory->id,
        'title' => 'Other category story',
        'content_type' => 'analysis',
        'importance' => 9,
        'status' => 'published',
        'published_at' => now()->subHours(4),
    ])->tags()->attach($sharedTag->id);

    $response = $this->getJson('/api/v1/articles?category=main&tags[0]=shared&content_type=analysis&importance_min=8&exclude_ids[0]='.$excludedArticle->id);

    $response->assertSuccessful()
        ->assertJsonPath('meta.total_results', 1)
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $matchingArticle->id);
});

it('returns featured and breaking article collections', function () {
    $category = Category::factory()->create(['slug' => 'main']);

    $featuredArticle = Article::factory()->create([
        'category_id' => $category->id,
        'is_featured' => true,
        'is_breaking' => false,
        'status' => 'published',
        'published_at' => now()->subHour(),
    ]);

    $breakingArticle = Article::factory()->create([
        'category_id' => $category->id,
        'is_featured' => false,
        'is_breaking' => true,
        'status' => 'published',
        'published_at' => now()->subHours(2),
    ]);

    Article::factory()->create([
        'category_id' => $category->id,
        'is_featured' => false,
        'is_breaking' => true,
        'status' => 'published',
        'published_at' => now()->subHours(30),
    ]);

    $this->getJson('/api/v1/articles/featured')
        ->assertSuccessful()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $featuredArticle->id);

    $this->getJson('/api/v1/articles/breaking')
        ->assertSuccessful()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $breakingArticle->id);
});

it('returns pinned articles for a category', function () {
    $mainCategory = Category::factory()->create(['slug' => 'main']);
    $otherCategory = Category::factory()->create(['slug' => 'sport']);

    $pinnedArticle = Article::factory()->create([
        'category_id' => $mainCategory->id,
        'is_pinned' => true,
        'status' => 'published',
        'published_at' => now()->subHour(),
    ]);

    Article::factory()->create([
        'category_id' => $otherCategory->id,
        'is_pinned' => true,
        'status' => 'published',
        'published_at' => now()->subHour(),
    ]);

    $this->getJson('/api/v1/category/main/pinned')
        ->assertSuccessful()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $pinnedArticle->id);
});

it('returns related articles from the same category excluding the current article', function () {
    $category = Category::factory()->create(['slug' => 'main']);

    $currentArticle = Article::factory()->create([
        'category_id' => $category->id,
        'status' => 'published',
        'published_at' => now()->subHour(),
    ]);

    $relatedArticle = Article::factory()->create([
        'category_id' => $category->id,
        'status' => 'published',
        'published_at' => now()->subHours(2),
    ]);

    Article::factory()->create([
        'status' => 'published',
        'published_at' => now()->subHours(3),
    ]);

    $this->getJson('/api/v1/articles/'.$currentArticle->slug.'/related')
        ->assertSuccessful()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $relatedArticle->id);
});

it('returns trending articles ordered by views within the recent window', function () {
    $category = Category::factory()->create(['slug' => 'main']);

    $highestViews = Article::factory()->create([
        'category_id' => $category->id,
        'views_count' => 150,
        'status' => 'published',
        'published_at' => now()->subHours(2),
    ]);

    $lowerViews = Article::factory()->create([
        'category_id' => $category->id,
        'views_count' => 50,
        'status' => 'published',
        'published_at' => now()->subHours(3),
    ]);

    Article::factory()->create([
        'category_id' => $category->id,
        'views_count' => 500,
        'status' => 'published',
        'published_at' => now()->subHours(60),
    ]);

    $this->getJson('/api/v1/articles/trending')
        ->assertSuccessful()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.id', $highestViews->id)
        ->assertJsonPath('data.1.id', $lowerViews->id);
});

it('returns similar articles with shared tags and same-category priority', function () {
    $mainCategory = Category::factory()->create(['slug' => 'main']);
    $otherCategory = Category::factory()->create(['slug' => 'sport']);
    $sharedTag = Tag::factory()->create(['slug' => 'shared']);
    $otherTag = Tag::factory()->create(['slug' => 'other']);

    $currentArticle = Article::factory()->create([
        'category_id' => $mainCategory->id,
        'status' => 'published',
        'published_at' => now()->subHour(),
    ]);
    $currentArticle->tags()->attach($sharedTag->id);

    $sameCategoryMatch = Article::factory()->create([
        'category_id' => $mainCategory->id,
        'status' => 'published',
        'published_at' => now()->subHours(2),
    ]);
    $sameCategoryMatch->tags()->attach($sharedTag->id);

    $otherCategoryMatch = Article::factory()->create([
        'category_id' => $otherCategory->id,
        'status' => 'published',
        'published_at' => now()->subMinutes(30),
    ]);
    $otherCategoryMatch->tags()->attach($sharedTag->id);

    $notSimilar = Article::factory()->create([
        'category_id' => $mainCategory->id,
        'status' => 'published',
        'published_at' => now()->subHours(3),
    ]);
    $notSimilar->tags()->attach($otherTag->id);

    $response = $this->getJson('/api/v1/articles/'.$currentArticle->slug.'/similar');

    $response->assertSuccessful()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.id', $sameCategoryMatch->id)
        ->assertJsonPath('data.1.id', $otherCategoryMatch->id);
});

it('returns an empty similar collection when the source article has no tags', function () {
    $article = Article::factory()->create([
        'status' => 'published',
        'published_at' => now()->subHour(),
    ]);

    $this->getJson('/api/v1/articles/'.$article->slug.'/similar')
        ->assertSuccessful()
        ->assertJsonCount(0, 'data')
        ->assertJsonPath('meta.total_results', 0);
});
