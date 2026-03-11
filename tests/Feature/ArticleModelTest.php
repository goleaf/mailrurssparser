<?php

use App\Models\Article;
use App\Models\ArticleView;
use App\Models\Category;
use App\Models\RssFeed;
use App\Models\Tag;
use App\Services\ArticleContentType;
use App\Services\ArticleStatus;
use App\Services\StorageDisk;
use Awcodes\Curator\Models\Media as CuratorMedia;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

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

it('supports reusable publication, category, and feed scopes', function () {
    $primaryCategory = Category::factory()->create();
    $secondaryCategory = Category::factory()->create();
    $primaryFeed = RssFeed::factory()->create(['category_id' => $primaryCategory->id]);
    $secondaryFeed = RssFeed::factory()->create(['category_id' => $secondaryCategory->id]);

    $matchingArticle = Article::factory()->create([
        'category_id' => $primaryCategory->id,
        'rss_feed_id' => $primaryFeed->id,
        'status' => 'published',
        'published_at' => now()->subHours(2),
    ]);

    $stalePrimaryArticle = Article::factory()->create([
        'category_id' => $primaryCategory->id,
        'rss_feed_id' => $primaryFeed->id,
        'status' => 'published',
        'published_at' => now()->subDays(2),
    ]);

    $secondaryRecentArticle = Article::factory()->create([
        'category_id' => $secondaryCategory->id,
        'rss_feed_id' => $secondaryFeed->id,
        'status' => 'published',
        'published_at' => now()->subHours(3),
    ]);

    $draftPrimaryArticle = Article::factory()->create([
        'category_id' => $primaryCategory->id,
        'rss_feed_id' => $primaryFeed->id,
        'status' => 'draft',
        'published_at' => now()->subHour(),
    ]);

    expect(Article::query()->publishedBetween(now()->subDay(), now())->pluck('id')->all())
        ->toContain($matchingArticle->id, $secondaryRecentArticle->id)
        ->not->toContain($stalePrimaryArticle->id, $draftPrimaryArticle->id)
        ->and(Article::query()->publishedSince(now()->subDay())->pluck('id')->all())
        ->toContain($matchingArticle->id, $secondaryRecentArticle->id)
        ->not->toContain($stalePrimaryArticle->id, $draftPrimaryArticle->id)
        ->and(Article::query()->publishedSince(now()->subDay())->inCategory($primaryCategory)->pluck('id')->all())->toBe([$matchingArticle->id])
        ->and(Article::query()->published()->fromFeed($primaryFeed)->pluck('id')->all())->toContain($matchingArticle->id, $stalePrimaryArticle->id)
        ->not->toContain($draftPrimaryArticle->id);
});

it('applies rolling breaking and recent windows', function () {
    $freshBreaking = Article::factory()->create([
        'status' => 'published',
        'is_breaking' => true,
        'published_at' => now()->minus(hours: 23),
    ]);

    $staleBreaking = Article::factory()->create([
        'status' => 'published',
        'is_breaking' => true,
        'published_at' => now()->minus(hours: 25),
    ]);

    $recentArticle = Article::factory()->create([
        'status' => 'published',
        'is_breaking' => false,
        'published_at' => now()->minus(hours: 5),
    ]);

    $staleArticle = Article::factory()->create([
        'status' => 'published',
        'is_breaking' => false,
        'published_at' => now()->minus(hours: 7),
    ]);

    expect(Article::query()->breaking()->pluck('id')->all())->toBe([$freshBreaking->id])
        ->and($recentArticle->is_recent)->toBeTrue()
        ->and($staleArticle->is_recent)->toBeFalse()
        ->and($staleBreaking->fresh()->is_recent)->toBeFalse();
});

it('supports reusable article view analytics scopes', function () {
    $article = Article::factory()->create();

    $viewerMatch = ArticleView::factory()->create([
        'article_id' => $article->id,
        'ip_hash' => 'ip-match',
        'session_hash' => 'session-match',
        'country_code' => 'DE',
        'timezone' => 'Europe/Berlin',
        'viewed_at' => now()->subMinutes(30),
    ]);

    $sessionMatch = ArticleView::factory()->create([
        'article_id' => $article->id,
        'ip_hash' => 'other-ip',
        'session_hash' => 'session-match',
        'country_code' => null,
        'timezone' => 'Europe/Paris',
        'viewed_at' => now()->subHours(2),
    ]);

    $outsideWindow = ArticleView::factory()->create([
        'article_id' => $article->id,
        'ip_hash' => 'ip-old',
        'session_hash' => 'session-old',
        'country_code' => 'FR',
        'timezone' => null,
        'viewed_at' => now()->subDays(2),
    ]);

    $otherArticleView = ArticleView::factory()->create([
        'viewed_at' => now()->subMinutes(10),
        'country_code' => 'US',
        'timezone' => 'America/New_York',
    ]);

    expect(ArticleView::query()->forArticle($article)->pluck('id')->all())->toContain($viewerMatch->id, $sessionMatch->id, $outsideWindow->id)
        ->not->toContain($otherArticleView->id)
        ->and(ArticleView::query()->forArticle($article)->matchingViewer('ip-match', 'session-match')->pluck('id')->all())->toContain($viewerMatch->id, $sessionMatch->id)
        ->and(ArticleView::query()->viewedBetween(now()->subHour(), now())->pluck('id')->all())->toContain($viewerMatch->id, $otherArticleView->id)
        ->not->toContain($outsideWindow->id)
        ->and(ArticleView::query()->viewedSince(now()->subHour())->pluck('id')->all())->toContain($viewerMatch->id, $otherArticleView->id)
        ->and(ArticleView::query()->withCountryCode()->pluck('id')->all())->toContain($viewerMatch->id, $outsideWindow->id, $otherArticleView->id)
        ->and(ArticleView::query()->withTimezone()->pluck('id')->all())->toContain($viewerMatch->id, $sessionMatch->id, $otherArticleView->id);
});

it('casts status and content type to translated enums', function () {
    $article = Article::factory()->create([
        'status' => ArticleStatus::Published->value,
        'content_type' => ArticleContentType::Analysis->value,
    ]);

    expect($article->fresh()->status)->toBe(ArticleStatus::Published)
        ->and($article->fresh()->status?->label('ru'))->toBe('Опубликовано')
        ->and($article->fresh()->content_type)->toBe(ArticleContentType::Analysis)
        ->and($article->fresh()->content_type?->label('ru'))->toBe('Аналитика');
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

it('can make transient articles without expanding parent factories', function () {
    expect(Category::query()->count())->toBe(0);

    $article = withoutExpandedFactoryRelationships(fn () => Article::factory()->make([
        'title' => 'Transient article',
    ]));

    expect($article->category_id)->toBeNull()
        ->and(Category::query()->count())->toBe(0);

    $persisted = Article::factory()->create();

    expect($persisted->category_id)->not->toBeNull()
        ->and(Category::query()->count())->toBe(1);
});

it('formats the reading time text', function () {
    $article = withoutExpandedFactoryRelationships(fn () => Article::factory()->make([
        'reading_time' => 7,
    ]));

    expect($article->reading_time_text)->toBe('7 мин чтения');
});

it('sanitizes malformed source names while keeping site-level mail source labels', function () {
    expect(Article::sanitizeSourceName('Новости Mail'))->toBe('Новости Mail')
        ->and(Article::sanitizeSourceName('Спорт Mail'))->toBe('Спорт Mail')
        ->and(Article::sanitizeSourceName('РБК'))->toBe('РБК')
        ->and(Article::sanitizeSourceName('Спортс"'))->toBe('Спортс')
        ->and(Article::sanitizeSourceName('Коммерсантъ-Новости'))->toBe('Коммерсантъ');
});

it('provides seo fallbacks and content accessors', function () {
    $article = withoutExpandedFactoryRelationships(fn () => Article::factory()->make([
        'title' => 'Important Update',
        'meta_title' => null,
        'meta_description' => null,
        'short_description' => str_repeat('short ', 40),
        'full_description' => '<p>Full content</p>',
        'rss_content' => '<p>RSS content</p>',
        'slug' => 'important-update',
        'canonical_url' => null,
    ]));

    $seoData = $article->getSeoData();

    expect($article->meta_title)->toBe('Important Update')
        ->and($article->meta_description)->toStartWith('short short')
        ->and($article->content)->toBe('<p>Full content</p>')
        ->and($seoData['canonical_url'])->toBe(route('articles.show', ['slug' => 'important-update']))
        ->and($seoData['structured_data'])->toBeArray();
});

it('prefers persisted seo relation overrides over imported article metadata', function () {
    $article = Article::factory()->create([
        'title' => 'Imported title',
        'meta_title' => 'Imported meta title',
        'meta_description' => 'Imported meta description',
        'canonical_url' => 'https://source.example.test/imported-title',
        'image_url' => 'https://cdn.example.test/imported.jpg',
        'status' => 'published',
        'published_at' => now()->subHour(),
    ]);

    $article->seo()->update([
        'title' => 'Editor controlled SEO title',
        'description' => 'Editor controlled SEO description',
        'image' => 'https://cdn.example.test/editor.jpg',
        'robots' => 'noindex, follow',
        'canonical_url' => 'https://news.example.test/articles/editor-controlled',
    ]);

    $seoData = $article->fresh()->getSeoData();

    expect($seoData['title'])->toBe('Editor controlled SEO title')
        ->and($seoData['description'])->toBe('Editor controlled SEO description')
        ->and($seoData['image'])->toBe('https://cdn.example.test/editor.jpg')
        ->and($seoData['robots'])->toBe('noindex, follow')
        ->and($seoData['canonical_url'])->toBe('https://news.example.test/articles/editor-controlled');
});

it('prefers spatie media library uploads over curator and rss image urls', function () {
    Storage::fake(StorageDisk::Public->value);

    $category = Category::factory()->create();

    Storage::disk(StorageDisk::Public->value)->put('curator/editorial-override.jpg', 'image-bytes');

    $curatorMedia = CuratorMedia::query()->create([
        'disk' => StorageDisk::Public->value,
        'directory' => 'curator',
        'visibility' => 'public',
        'name' => 'editorial-override',
        'path' => 'curator/editorial-override.jpg',
        'width' => 1600,
        'height' => 900,
        'size' => 1024,
        'type' => 'image',
        'ext' => 'jpg',
    ]);

    $article = Article::factory()->create([
        'category_id' => $category->id,
        'image_url' => 'https://rss.example.test/imported.jpg',
        'curator_media_id' => $curatorMedia->id,
        'status' => 'published',
        'published_at' => now()->subHour(),
    ]);

    $upload = UploadedFile::fake()->image('featured.jpg', 1600, 900);

    $article
        ->addMedia($upload->getRealPath())
        ->usingFileName('featured.jpg')
        ->usingName('featured')
        ->toMediaCollection('featured_image', StorageDisk::Public->value);

    $article = $article->fresh();
    $featuredImage = $article->getFirstMedia('featured_image');

    expect($featuredImage)->not->toBeNull()
        ->and($article->effective_image_url)
        ->toBe($featuredImage?->getAvailableUrl(['hero', 'card', 'thumb']))
        ->and($article->effective_image_url)
        ->not->toBe('https://rss.example.test/imported.jpg')
        ->and($article->effective_image_url)
        ->not->toBe(Storage::disk(StorageDisk::Public->value)->url('curator/editorial-override.jpg'));
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

it('search scope matches title, body, author, and source fields', function () {
    $bodyMatch = Article::factory()->create([
        'title' => 'Energy crisis briefing',
        'short_description' => 'Detailed energy market overview',
        'full_description' => 'Analysis of the current energy market',
        'author' => 'Unrelated Author',
        'source_name' => 'Daily Wire',
    ]);

    $authorMatch = Article::factory()->create([
        'title' => 'Daily summary',
        'short_description' => 'General digest',
        'full_description' => 'Nothing about the requested term here',
        'author' => 'Energy Insider',
        'source_name' => 'Local Desk',
    ]);

    $sourceMatch = Article::factory()->create([
        'title' => 'Market wrap',
        'short_description' => 'General digest',
        'full_description' => 'Nothing about the requested term here',
        'author' => 'Unrelated Author',
        'source_name' => 'Energy Daily',
    ]);

    Article::factory()->create([
        'title' => 'Daily summary',
        'short_description' => 'General digest',
        'full_description' => 'Nothing about the requested term here',
        'author' => 'Unrelated Author',
        'source_name' => 'Local Desk',
    ]);

    $matchingIds = Article::query()->search('energy')->pluck('id')->all();

    expect($matchingIds)->toHaveCount(3)
        ->and($matchingIds)->toContain(
            $bodyMatch->id,
            $authorMatch->id,
            $sourceMatch->id,
        );
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
