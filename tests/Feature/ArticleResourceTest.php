<?php

use App\Http\Resources\ArticleCollection;
use App\Http\Resources\ArticleListResource;
use App\Http\Resources\ArticleResource;
use App\Http\Resources\CategoryResource;
use App\Http\Resources\TagResource;
use App\Models\Article;
use App\Models\Category;
use App\Models\SubCategory;
use App\Models\Tag;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    if (! trait_exists(Laravel\Scout\Searchable::class)) {
        $this->markTestSkipped('Laravel Scout is not installed.');
    }
});

it('builds the article show resource payload', function () {
    $category = Category::factory()->create([
        'name' => 'Politics',
        'slug' => 'politics',
        'color' => '#DC2626',
        'icon' => '🏛️',
    ]);

    $subCategory = SubCategory::factory()->create([
        'category_id' => $category->id,
        'name' => 'Local',
        'slug' => 'local',
    ]);

    $article = Article::factory()->create([
        'category_id' => $category->id,
        'sub_category_id' => $subCategory->id,
        'title' => 'Test Article',
        'short_description' => 'Short',
        'full_description' => 'Full',
        'rss_content' => 'Fallback',
        'meta_title' => null,
        'meta_description' => null,
        'structured_data' => null,
        'reading_time' => 5,
        'is_breaking' => true,
        'is_pinned' => true,
        'is_editors_choice' => true,
        'is_sponsored' => false,
        'views_count' => 120,
        'shares_count' => 8,
        'bookmarks_count' => 3,
        'published_at' => now(),
    ]);

    $tag = Tag::factory()->create([
        'name' => 'Top',
        'slug' => 'top',
        'color' => '#000000',
    ]);

    $article->tags()->attach($tag);
    $article->forceFill(['reading_time' => 5])->saveQuietly();
    $article->refresh();
    $article->setAttribute('related_ids', [10, 20]);

    $route = Route::get('/articles/{article}', fn () => null)->name('api.articles.show');
    $request = Request::create('/articles/'.$article->id, 'GET');
    $request->setRouteResolver(fn () => $route);

    $resource = (new ArticleResource($article->load(['category', 'subCategory', 'tags'])))
        ->toArray($request);

    expect($resource['id'])->toBe($article->id)
        ->and($resource['title'])->toBe('Test Article')
        ->and($resource['reading_time_text'])->toBe('5 мин чтения')
        ->and($resource['category']['slug'])->toBe('politics')
        ->and($resource['sub_category']['slug'])->toBe('local')
        ->and($resource['tags'])->toHaveCount(1)
        ->and($resource['tags'][0]['id'])->toBe($tag->id)
        ->and($resource['full_content'])->toBe('Full')
        ->and($resource['meta_title'])->toBe('Test Article')
        ->and($resource['meta_description'])->not->toBeNull()
        ->and($resource['structured_data'])->toBeArray()
        ->and($resource['related_ids'])->toBe([10, 20])
        ->and($resource['published_at'])->not->toBeNull()
        ->and($resource['published_at_human'])->not->toBeNull()
        ->and($resource['published_at_date'])->not->toBeNull()
        ->and($resource['is_recent'])->toBeTrue();
});

it('builds the article list resource without show-only fields', function () {
    $category = Category::factory()->create();
    $article = Article::factory()->create([
        'category_id' => $category->id,
        'status' => 'published',
        'published_at' => now()->subHour(),
    ]);

    $resource = (new ArticleListResource($article->load('category')))
        ->toArray(Request::create('/articles', 'GET'));

    expect($resource)
        ->toHaveKey('reading_time_text')
        ->not->toHaveKey('full_content')
        ->not->toHaveKey('meta_title')
        ->not->toHaveKey('meta_description')
        ->not->toHaveKey('structured_data')
        ->not->toHaveKey('related_ids');
});

it('builds the category resource payload', function () {
    $category = Category::factory()->create([
        'name' => 'Politics',
        'slug' => 'politics',
        'articles_count_cache' => 12,
    ]);
    $subCategory = SubCategory::factory()->create([
        'category_id' => $category->id,
        'name' => 'Local',
        'slug' => 'local',
    ]);

    $resource = (new CategoryResource($category->load('subCategories')))
        ->toArray(Request::create('/categories', 'GET'));

    expect($resource)->toBe([
        'id' => $category->id,
        'name' => 'Politics',
        'slug' => 'politics',
        'color' => $category->color,
        'icon' => $category->icon,
        'description' => $category->description,
        'articles_count_cache' => 12,
        'sub_categories' => [
            [
                'id' => $subCategory->id,
                'name' => 'Local',
                'slug' => 'local',
            ],
        ],
    ]);
});

it('builds the tag resource payload', function () {
    $tag = Tag::factory()->create([
        'name' => 'Top',
        'slug' => 'top',
        'color' => '#111111',
        'usage_count' => 44,
        'is_trending' => true,
        'is_featured' => false,
    ]);

    $resource = (new TagResource($tag))
        ->toArray(Request::create('/tags', 'GET'));

    expect($resource)->toBe([
        'id' => $tag->id,
        'name' => 'Top',
        'slug' => 'top',
        'color' => '#111111',
        'usage_count' => 44,
        'is_trending' => true,
        'is_featured' => false,
    ]);
});

it('wraps collections with article list resources and meta data', function () {
    $topCategory = Category::factory()->create([
        'name' => 'Main',
        'slug' => 'main',
        'order' => 1,
    ]);
    Category::factory()->count(5)->create();

    Article::factory()->count(3)->create([
        'category_id' => $topCategory->id,
        'status' => 'published',
        'published_at' => now()->subHour(),
    ]);

    $article = Article::factory()->create([
        'category_id' => $topCategory->id,
        'status' => 'published',
        'published_at' => now()->subHour(),
    ]);

    $collection = new ArticleCollection(collect([$article->load('category')]));
    $responseData = $collection->response()->getData(true);

    expect($responseData)
        ->toHaveKey('data')
        ->toHaveKey('meta')
        ->and($responseData['data'])->toHaveCount(1)
        ->and($responseData['data'][0])->not->toHaveKey('full_content')
        ->and($responseData['meta'])->toHaveKey('categories_summary')
        ->and($responseData['meta'])->toHaveKey('total_results')
        ->and($responseData['meta']['total_results'])->toBe(1)
        ->and($responseData['meta']['categories_summary'])->toHaveCount(5)
        ->and($responseData['meta']['categories_summary'][0]['slug'])->toBe('main');
});
