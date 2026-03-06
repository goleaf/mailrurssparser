<?php

use App\Models\Article;
use App\Models\ArticleView;
use App\Models\Tag;

beforeEach(function () {
    if (! trait_exists(Laravel\Scout\Searchable::class)) {
        $this->markTestSkipped('Laravel Scout is not installed.');
    }
});

it('generates unique slugs from the title when missing', function () {
    $first = Article::factory()->create([
        'title' => 'Breaking News',
        'slug' => '',
    ]);

    $second = Article::factory()->create([
        'title' => 'Breaking News',
        'slug' => '',
    ]);

    expect($first->slug)->toBe('breaking-news')
        ->and($second->slug)->toBe('breaking-news-2');
});

it('scopes published articles', function () {
    $published = Article::factory()->create([
        'status' => 'published',
        'published_at' => now()->subDay(),
    ]);

    Article::factory()->create([
        'status' => 'published',
        'published_at' => now()->addDay(),
    ]);

    Article::factory()->create([
        'status' => 'draft',
        'published_at' => now()->subDay(),
    ]);

    expect(Article::published()->pluck('id')->all())->toBe([$published->id]);
});

it('syncs tags and updates usage counts', function () {
    $article = Article::factory()->create();
    $first = Tag::factory()->create(['usage_count' => 0]);
    $second = Tag::factory()->create(['usage_count' => 0]);

    $article->syncTags([$first->id, $second->id]);

    expect($first->refresh()->usage_count)->toBe(1)
        ->and($second->refresh()->usage_count)->toBe(1);

    $article->syncTags([$second->id]);

    expect($first->refresh()->usage_count)->toBe(0)
        ->and($second->refresh()->usage_count)->toBe(1);
});

it('formats the reading time text', function () {
    $article = Article::factory()->make(['reading_time' => 7]);

    expect($article->reading_time_text)->toBe('7 мин чтения');
});

it('increments views once per ip per hour', function () {
    $article = Article::factory()->create(['views_count' => 0]);

    $article->incrementViews('203.0.113.10', 'session-1');

    expect($article->refresh()->views_count)->toBe(1)
        ->and(ArticleView::query()->where('article_id', $article->id)->count())->toBe(1);

    $article->incrementViews('203.0.113.10', 'session-1');

    expect($article->refresh()->views_count)->toBe(1)
        ->and(ArticleView::query()->where('article_id', $article->id)->count())->toBe(1);
});

it('resolves encoded ids in queries', function () {
    $article = Article::factory()->create();

    $encodedId = $article->id_encoded;

    expect($encodedId)->toBeString()->not->toBe('');

    $resolved = Article::find($encodedId);

    expect($resolved)->not->toBeNull()
        ->and($resolved->is($article))->toBeTrue()
        ->and(Article::query()->where('id', $encodedId)->exists())->toBeTrue();
});
