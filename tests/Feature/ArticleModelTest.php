<?php

use App\Models\Article;
use App\Models\ArticleView;
use App\Models\Tag;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    if (! trait_exists(Laravel\Scout\Searchable::class)) {
        $this->markTestSkipped('Laravel Scout is not installed.');
    }
});

it('generates unique slugs from the title when missing', function () {
    $first = Article::factory()->create([
        'title' => 'Breaking News',
        'slug' => '',
        'rss_content' => str_repeat('word ', 240),
        'reading_time' => 99,
    ]);

    $second = Article::factory()->create([
        'title' => 'Breaking News',
        'slug' => '',
        'rss_content' => str_repeat('word ', 240),
        'reading_time' => 99,
    ]);

    expect($first->slug)->toBe('breaking-news')
        ->and($second->slug)->toBe('breaking-news-2')
        ->and($first->reading_time)->toBe(2)
        ->and($second->reading_time)->toBe(2);
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
        ->and($second->refresh()->usage_count)->toBe(1)
        ->and(DB::table('article_tag')
            ->where('article_id', $article->id)
            ->where('tag_id', $first->id)
            ->value('created_at'))
        ->not->toBeNull();

    $article->syncTags([$second->id]);

    expect($first->refresh()->usage_count)->toBe(0)
        ->and($second->refresh()->usage_count)->toBe(1);
});

it('formats the reading time text', function () {
    $article = Article::factory()->make(['reading_time' => 7]);

    expect($article->reading_time_text)->toBe('7 мин чтения');
});

it('provides seo fallbacks and content accessors', function () {
    $article = Article::factory()->make([
        'title' => 'Important Update',
        'meta_title' => null,
        'meta_description' => null,
        'short_description' => str_repeat('short ', 40),
        'full_description' => '<p>Full content</p>',
        'rss_content' => '<p>RSS content</p>',
        'slug' => 'important-update',
        'canonical_url' => null,
    ]);

    $seoData = $article->getSeoData();

    expect($article->meta_title)->toBe('Important Update')
        ->and($article->meta_description)->toStartWith('short short')
        ->and($article->content)->toBe('<p>Full content</p>')
        ->and($seoData['canonical_url'])->toBe(rtrim((string) config('app.url'), '/').'/#/articles/important-update')
        ->and($seoData['structured_data'])->toBeArray();
});

it('recalculates reading time and last edited timestamp when content changes', function () {
    $article = Article::factory()->create([
        'full_description' => 'short text',
        'rss_content' => 'short text',
        'reading_time' => 1,
        'last_edited_at' => null,
    ]);

    $article->update([
        'full_description' => str_repeat('word ', 900),
    ]);

    expect($article->fresh()->reading_time)->toBeGreaterThan(1)
        ->and($article->fresh()->last_edited_at)->not->toBeNull();
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

it('increments shares and recalculates engagement score', function () {
    $article = Article::factory()->create([
        'views_count' => 10,
        'shares_count' => 0,
        'bookmarks_count' => 2,
        'importance' => 8,
        'engagement_score' => 0,
    ]);

    $article->incrementShares();
    $article->recalculateEngagementScore();

    expect($article->fresh()->shares_count)->toBe(1)
        ->and($article->fresh()->engagement_score)->toBe(101.0);
});

it('search scope only matches title and description fields', function () {
    $matchingArticle = Article::factory()->create([
        'title' => 'Energy crisis briefing',
        'short_description' => 'Detailed energy market overview',
        'full_description' => 'Analysis of the current energy market',
        'author' => 'Unrelated Author',
    ]);

    Article::factory()->create([
        'title' => 'Daily summary',
        'short_description' => 'General digest',
        'full_description' => 'Nothing about the requested term here',
        'author' => 'Energy Insider',
    ]);

    expect(Article::query()->search('energy')->pluck('id')->all())->toBe([$matchingArticle->id]);
});

it('decrements tag usage counts when an article is deleted', function () {
    $article = Article::factory()->create();
    $tag = Tag::factory()->create(['usage_count' => 0]);

    $article->syncTags([$tag->id]);
    $article->delete();

    expect($tag->fresh()->usage_count)->toBe(0);
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
