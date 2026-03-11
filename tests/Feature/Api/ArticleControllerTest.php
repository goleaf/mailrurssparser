<?php

use App\Models\Article;
use App\Models\Category;
use App\Models\SubCategory;
use App\Models\Tag;
use App\Services\ArticleContentType;
use App\Services\ArticleStatus;

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

it('filters the index by sub category and returns the serialized sub category payload', function () {
    $category = Category::factory()->create(['slug' => 'society']);
    $matchingSubCategory = SubCategory::factory()->create([
        'category_id' => $category->id,
        'name' => 'Жизнь',
        'slug' => 'zhizn',
    ]);
    $otherSubCategory = SubCategory::factory()->create([
        'category_id' => $category->id,
        'name' => 'Город',
        'slug' => 'gorod',
    ]);

    $matchingArticle = Article::factory()->create([
        'category_id' => $category->id,
        'sub_category_id' => $matchingSubCategory->id,
        'status' => 'published',
        'published_at' => now()->subHour(),
    ]);

    Article::factory()->create([
        'category_id' => $category->id,
        'sub_category_id' => $otherSubCategory->id,
        'status' => 'published',
        'published_at' => now()->subHours(2),
    ]);

    $this->getJson('/api/v1/articles?category=society&sub=zhizn&per_page=10')
        ->assertSuccessful()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $matchingArticle->id)
        ->assertJsonPath('data.0.sub_category.id', $matchingSubCategory->id)
        ->assertJsonPath('data.0.sub_category.slug', 'zhizn');
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
        ->assertJsonPath('data.0.id', $relatedArticle->id);
});

it('embeds related content collections directly in the show payload', function () {
    $mainCategory = Category::factory()->create(['slug' => 'main']);
    $otherCategory = Category::factory()->create(['slug' => 'world']);
    $sharedTag = Tag::factory()->create(['slug' => 'shared']);

    $currentArticle = Article::factory()->create([
        'category_id' => $mainCategory->id,
        'title' => 'AI market update in europe',
        'short_description' => 'Weekly europe market analysis',
        'content_type' => 'analysis',
        'status' => 'published',
        'published_at' => now()->subHour(),
    ]);
    $currentArticle->tags()->attach($sharedTag->id);

    $relatedArticle = Article::factory()->create([
        'category_id' => $mainCategory->id,
        'title' => 'AI market forecast for europe',
        'short_description' => 'Europe analysis with shared outlook',
        'content_type' => 'analysis',
        'status' => 'published',
        'published_at' => now()->subHours(2),
    ]);
    $relatedArticle->tags()->attach($sharedTag->id);

    $similarArticle = Article::factory()->create([
        'category_id' => $otherCategory->id,
        'title' => 'AI market signals from europe',
        'short_description' => 'Shared europe analysis and outlook',
        'content_type' => 'analysis',
        'status' => 'published',
        'published_at' => now()->subHours(3),
    ]);
    $similarArticle->tags()->attach($sharedTag->id);

    $response = $this->getJson('/api/v1/articles/'.$currentArticle->slug);

    $response->assertSuccessful()
        ->assertJsonStructure([
            'data' => [
                'status_label',
                'content_type_label',
                'related_ids',
                'related_articles',
                'similar_articles',
                'more_from_category',
            ],
        ])
        ->assertJsonPath('data.status', ArticleStatus::Published->value)
        ->assertJsonPath('data.status_label', 'Опубликовано')
        ->assertJsonPath('data.content_type', ArticleContentType::Analysis->value)
        ->assertJsonPath('data.content_type_label', 'Аналитика')
        ->assertJsonPath('data.related_articles.0.id', $relatedArticle->id)
        ->assertJsonPath('data.similar_articles.0.id', $similarArticle->id);
});

it('can fetch an article without tracking a new view and returns cache headers', function () {
    $article = Article::factory()->create([
        'status' => 'published',
        'published_at' => now()->subHour(),
        'views_count' => 7,
    ]);
    $article->seo()->update([
        'title' => 'API SEO title',
        'description' => 'API SEO description',
        'image' => 'https://cdn.example.test/articles/api.jpg',
        'canonical_url' => 'https://news.example.test/articles/api-seo-title',
        'robots' => 'index, follow',
    ]);

    $response = $this->getJson('/api/v1/articles/'.$article->slug.'?track=0');

    $response->assertSuccessful()
        ->assertHeaderContains('Cache-Control', 'public')
        ->assertHeaderContains('Cache-Control', 'max-age=60')
        ->assertJsonPath('data.views_count', 7)
        ->assertJsonPath('data.seo.title', 'API SEO title')
        ->assertJsonPath('data.seo.canonical_url', 'https://news.example.test/articles/api-seo-title');

    expect($article->fresh()->views_count)->toBe(7);
});

it('can fetch an article by numeric id in the show endpoint', function () {
    $article = Article::factory()->create([
        'status' => 'published',
        'published_at' => now()->subHour(),
        'title' => 'Numeric route article',
    ]);

    $this->getJson('/api/v1/articles/'.$article->id.'?track=0')
        ->assertSuccessful()
        ->assertJsonPath('data.id', $article->id)
        ->assertJsonPath('data.slug', $article->slug)
        ->assertJsonPath('data.title', 'Numeric route article');
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
        'title' => 'Signal alpha outlook',
        'short_description' => 'Signal alpha focus piece',
        'full_description' => null,
        'rss_content' => null,
        'author' => 'Editorial Desk',
        'source_name' => 'Main Feed',
        'status' => 'published',
        'published_at' => now()->subHour(),
    ]);
    $currentArticle->tags()->attach($sharedTag->id);

    $sameCategoryMatch = Article::factory()->create([
        'category_id' => $mainCategory->id,
        'title' => 'Signal alpha market brief',
        'short_description' => 'Shared signal alpha story',
        'full_description' => null,
        'rss_content' => null,
        'author' => 'Different Author',
        'source_name' => 'Different Feed',
        'status' => 'published',
        'published_at' => now()->subHours(2),
    ]);
    $sameCategoryMatch->tags()->attach($sharedTag->id);

    $otherCategoryMatch = Article::factory()->create([
        'category_id' => $otherCategory->id,
        'title' => 'Signal alpha global update',
        'short_description' => 'Shared alpha context abroad',
        'full_description' => null,
        'rss_content' => null,
        'author' => 'World Desk',
        'source_name' => 'World Feed',
        'status' => 'published',
        'published_at' => now()->subMinutes(30),
    ]);
    $otherCategoryMatch->tags()->attach($sharedTag->id);

    $notSimilar = Article::factory()->create([
        'category_id' => $mainCategory->id,
        'title' => 'Completely unrelated weather bulletin',
        'short_description' => 'No overlap with the topic',
        'full_description' => null,
        'rss_content' => null,
        'author' => 'Weather Desk',
        'source_name' => 'Weather Feed',
        'status' => 'published',
        'published_at' => now()->subHours(3),
    ]);
    $notSimilar->tags()->attach($otherTag->id);

    $response = $this->getJson('/api/v1/articles/'.$currentArticle->slug.'/similar');

    $response->assertSuccessful();

    $similarIds = collect($response->json('data'))->pluck('id');

    expect($similarIds)->toContain($sameCategoryMatch->id, $otherCategoryMatch->id);
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
