<?php

use App\Models\Article;
use App\Models\Category;
use App\Models\Tag;

it('validates the search term', function () {
    $this->getJson('/api/v1/search')
        ->assertUnprocessable();
});

it('returns search results with query and total meta', function () {
    if (! trait_exists(Laravel\Scout\Searchable::class)) {
        $this->markTestSkipped('Laravel Scout is not installed.');
    }

    $category = Category::factory()->create(['name' => 'Politics', 'slug' => 'politics']);

    $article = Article::factory()->create([
        'category_id' => $category->id,
        'title' => 'Hello world',
        'short_description' => 'Hello description',
        'status' => 'published',
        'published_at' => now()->subHour(),
    ]);

    $this->getJson('/api/v1/search?'.http_build_query([
        'q' => 'Hello',
        'category' => 'politics',
        'sort' => 'relevance',
    ]))
        ->assertSuccessful()
        ->assertJsonPath('data.0.id', $article->id)
        ->assertJsonPath('meta.query', 'Hello')
        ->assertJsonPath('meta.total', 1);
});

it('returns fallback suggestions when no search results exist', function () {
    $category = Category::factory()->create([
        'name' => 'Спорт',
        'slug' => 'sport',
        'color' => '#0891B2',
    ]);
    $tag = Tag::factory()->create([
        'name' => 'Спорт',
        'slug' => 'sport',
        'color' => '#6B7280',
        'usage_count' => 50,
    ]);

    $response = $this->getJson('/api/v1/search?'.http_build_query(['q' => 'Спорт']));

    $response->assertSuccessful()
        ->assertJsonPath('meta.total', 0)
        ->assertJsonCount(2, 'meta.suggestions');

    expect($response->json('meta.suggestions'))->toContain([
        'type' => 'category',
        'id' => $category->id,
        'name' => 'Спорт',
        'slug' => 'sport',
        'color' => '#0891B2',
    ])->toContain([
        'type' => 'tag',
        'id' => $tag->id,
        'name' => 'Спорт',
        'slug' => 'sport',
        'color' => '#6B7280',
    ]);
});

it('returns autocomplete suggestions for articles, categories, and tags', function () {
    $category = Category::factory()->create(['name' => 'Спорт', 'slug' => 'sport']);
    $tag = Tag::factory()->create(['name' => 'Спорт', 'slug' => 'sport']);

    Article::factory()->create([
        'category_id' => $category->id,
        'title' => 'Спорт сегодня',
        'status' => 'published',
        'published_at' => now()->subHour(),
    ]);

    $this->getJson('/api/v1/search/suggest?'.http_build_query(['q' => 'Спорт']))
        ->assertSuccessful()
        ->assertJsonStructure([
            'articles',
            'categories',
            'tags',
        ])
        ->assertJsonFragment([
            'name' => $category->name,
            'slug' => $category->slug,
            'color' => $category->color,
        ])
        ->assertJsonFragment([
            'name' => $tag->name,
            'slug' => $tag->slug,
        ]);
});

it('returns highlighted excerpts for matching article content', function () {
    $article = Article::factory()->create([
        'full_description' => 'Первое предложение. Важный матч сегодня решит сезон. Заключение.',
        'rss_content' => null,
        'status' => 'published',
        'published_at' => now()->subHour(),
    ]);

    $this->getJson('/api/v1/search/highlights?'.http_build_query([
        'q' => 'матч',
        'article_id' => $article->id,
    ]))
        ->assertSuccessful()
        ->assertJsonPath('excerpt', 'Важный <mark>матч</mark> сегодня решит сезон.');
});
