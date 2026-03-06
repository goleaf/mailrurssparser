<?php

use App\Http\Resources\ArticleCollection;
use App\Http\Resources\ArticleResource;
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

it('builds the article resource payload', function () {
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
        'published_at' => now(),
    ]);

    $tag = Tag::factory()->create([
        'name' => 'Top',
        'slug' => 'top',
        'color' => '#000000',
    ]);

    $article->tags()->attach($tag);

    Route::get('/articles/{article}', fn () => null)->name('api.articles.show');
    $request = Request::create('/articles/'.$article->id, 'GET');
    $request->setRouteResolver(fn () => Route::current());

    $resource = (new ArticleResource($article->load(['category', 'subCategory', 'tags'])))
        ->toArray($request);

    expect($resource['id'])->toBe($article->id)
        ->and($resource['title'])->toBe('Test Article')
        ->and($resource['category']['slug'])->toBe('politics')
        ->and($resource['sub_category']['slug'])->toBe('local')
        ->and($resource['tags'])->toHaveCount(1)
        ->and($resource['full_description'])->toBe('Full');
});

it('wraps collections with article resource', function () {
    $category = Category::factory()->create();

    $article = Article::factory()->create([
        'category_id' => $category->id,
    ]);

    $collection = new ArticleCollection(collect([$article->load('category')]));

    $data = $collection->toArray(Request::create('/articles', 'GET'));

    expect($data)->toHaveKey('data')
        ->and($data['data'])->toHaveCount(1);
});
