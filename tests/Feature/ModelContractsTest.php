<?php

use App\Models\Bookmark;
use App\Models\Category;
use App\Models\NewsletterSubscriber;
use App\Models\RssFeed;
use App\Models\RssParseLog;
use App\Models\SubCategory;
use App\Models\Tag;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

it('defines the requested category model contract', function () {
    $category = new Category;

    expect($category->getFillable())->toBe([
        'name',
        'slug',
        'rss_url',
        'rss_key',
        'color',
        'icon',
        'meta_title',
        'meta_description',
        'curator_cover_id',
        'description',
        'order',
        'is_active',
        'show_in_menu',
        'articles_count_cache',
    ])
        ->and($category->getCasts())->toMatchArray([
            'is_active' => 'boolean',
            'show_in_menu' => 'boolean',
        ])
        ->and($category->subCategories())->toBeInstanceOf(HasMany::class)
        ->and($category->articles())->toBeInstanceOf(HasMany::class)
        ->and($category->rssFeeds())->toBeInstanceOf(HasMany::class);
});

it('defines the requested sub category model contract', function () {
    $subCategory = new SubCategory;

    expect($subCategory->getFillable())->toBe([
        'category_id',
        'name',
        'slug',
        'description',
        'color',
        'icon',
        'is_active',
        'order',
    ])
        ->and($subCategory->getCasts())->toMatchArray([
            'is_active' => 'boolean',
        ])
        ->and($subCategory->category())->toBeInstanceOf(BelongsTo::class)
        ->and($subCategory->articles())->toBeInstanceOf(HasMany::class);
});

it('defines the requested tag model contract', function () {
    $tag = new Tag;

    expect($tag->getFillable())->toBe([
        'name',
        'slug',
        'color',
        'description',
        'usage_count',
        'is_trending',
        'is_featured',
    ])
        ->and($tag->getCasts())->toMatchArray([
            'usage_count' => 'integer',
            'is_trending' => 'boolean',
            'is_featured' => 'boolean',
        ])
        ->and($tag->articles())->toBeInstanceOf(BelongsToMany::class);
});

it('supports the requested tag scopes and usage increment helper', function () {
    $featuredTrendingTag = Tag::factory()->create([
        'usage_count' => 4,
        'is_trending' => true,
        'is_featured' => true,
    ]);
    $popularTag = Tag::factory()->create([
        'usage_count' => 10,
        'is_trending' => false,
        'is_featured' => false,
    ]);

    expect(Tag::trending()->pluck('id')->all())->toBe([$featuredTrendingTag->id])
        ->and(Tag::featured()->pluck('id')->all())->toBe([$featuredTrendingTag->id])
        ->and(Tag::popular()->pluck('id')->all())->toBe([$popularTag->id, $featuredTrendingTag->id]);

    $featuredTrendingTag->incrementUsage();

    expect($featuredTrendingTag->fresh()->usage_count)->toBe(5);
});

it('defines the requested rss feed model contract', function () {
    $rssFeed = new RssFeed;

    expect($rssFeed->getFillable())->toBe([
        'category_id',
        'curator_logo_id',
        'title',
        'url',
        'source_name',
        'language',
        'is_active',
        'auto_publish',
        'auto_featured',
        'fetch_interval',
        'last_parsed_at',
        'next_parse_at',
        'articles_parsed_total',
        'last_run_new_count',
        'last_run_skip_count',
        'last_run_error_count',
        'consecutive_failures',
        'last_error',
        'extra_settings',
    ])
        ->and($rssFeed->getCasts())->toMatchArray([
            'is_active' => 'boolean',
            'auto_publish' => 'boolean',
            'auto_featured' => 'boolean',
            'last_parsed_at' => 'datetime',
            'next_parse_at' => 'datetime',
            'extra_settings' => 'array',
        ])
        ->and($rssFeed->category())->toBeInstanceOf(BelongsTo::class)
        ->and($rssFeed->articles())->toBeInstanceOf(HasMany::class)
        ->and($rssFeed->parseLogs())->toBeInstanceOf(HasMany::class);
});

it('supports the requested rss feed scopes and lifecycle helpers', function () {
    Carbon::setTestNow('2026-03-07 12:00:00');

    $category = Category::factory()->create();
    $otherCategory = Category::factory()->create();
    $dueFeed = RssFeed::factory()->create([
        'category_id' => $category->id,
        'is_active' => true,
        'next_parse_at' => now()->subMinute(),
        'fetch_interval' => 15,
        'articles_parsed_total' => 8,
        'consecutive_failures' => 2,
        'last_error' => 'old error',
        'last_parsed_at' => now()->subMinutes(30),
    ]);
    $futureFeed = RssFeed::factory()->create([
        'category_id' => $category->id,
        'is_active' => true,
        'next_parse_at' => now()->addMinute(),
        'last_parsed_at' => null,
    ]);
    $inactiveFeed = RssFeed::factory()->create([
        'category_id' => $otherCategory->id,
        'is_active' => false,
        'next_parse_at' => now()->subMinute(),
        'last_error' => 'disabled error',
        'last_parsed_at' => null,
    ]);

    expect(RssFeed::active()->pluck('id')->all())->toContain($dueFeed->id, $futureFeed->id)
        ->not->toContain($inactiveFeed->id)
        ->and(RssFeed::query()->inCategory($category)->pluck('id')->all())->toContain($dueFeed->id, $futureFeed->id)
        ->not->toContain($inactiveFeed->id)
        ->and(RssFeed::query()->parsed()->pluck('id')->all())->toBe([$dueFeed->id])
        ->and(RssFeed::query()->withErrors()->pluck('id')->all())->toContain($dueFeed->id, $inactiveFeed->id)
        ->and(RssFeed::dueForParsing()->pluck('id')->all())->toBe([$dueFeed->id]);

    $dueFeed->markParsed(3, 1, 2);

    expect($dueFeed->fresh())
        ->articles_parsed_total->toBe(11)
        ->last_run_new_count->toBe(3)
        ->last_run_skip_count->toBe(1)
        ->last_run_error_count->toBe(2)
        ->consecutive_failures->toBe(0)
        ->last_error->toBeNull()
        ->next_parse_at->toEqual(now()->addMinutes(15));

    $futureFeed->update([
        'consecutive_failures' => 9,
        'last_run_error_count' => 0,
    ]);

    $futureFeed->markFailed('Feed timeout');

    expect($futureFeed->fresh())
        ->consecutive_failures->toBe(10)
        ->last_error->toBe('Feed timeout')
        ->last_run_error_count->toBe(1)
        ->is_active->toBeFalse();

    Carbon::setTestNow();
});

it('defines the requested rss parse log model contract', function () {
    $rssParseLog = new RssParseLog;

    expect($rssParseLog->getFillable())->toBe([
        'rss_feed_id',
        'started_at',
        'finished_at',
        'new_count',
        'skip_count',
        'error_count',
        'total_items',
        'duration_ms',
        'success',
        'error_message',
        'item_errors',
        'triggered_by',
    ])
        ->and($rssParseLog->getCasts())->toMatchArray([
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'success' => 'boolean',
            'item_errors' => 'array',
        ])
        ->and($rssParseLog->rssFeed())->toBeInstanceOf(BelongsTo::class);
});

it('supports rss parse log time window scopes', function () {
    Carbon::setTestNow('2026-03-08 12:00:00');

    $runningCompletedLog = RssParseLog::factory()->create([
        'started_at' => Carbon::parse('2026-03-08 11:45:00'),
        'finished_at' => Carbon::parse('2026-03-08 12:15:00'),
    ]);
    $runningOpenLog = RssParseLog::factory()->create([
        'started_at' => Carbon::parse('2026-03-08 11:55:00'),
        'finished_at' => null,
    ]);
    $finishedLog = RssParseLog::factory()->create([
        'started_at' => Carbon::parse('2026-03-08 10:00:00'),
        'finished_at' => Carbon::parse('2026-03-08 10:30:00'),
    ]);
    $futureLog = RssParseLog::factory()->create([
        'started_at' => Carbon::parse('2026-03-08 12:30:00'),
        'finished_at' => null,
    ]);

    expect(RssParseLog::query()->runningAt(now())->pluck('id')->all())
        ->toContain($runningCompletedLog->id, $runningOpenLog->id)
        ->not->toContain($finishedLog->id)
        ->not->toContain($futureLog->id)
        ->and(
            RssParseLog::query()
                ->overlappingWindow('2026-03-08 11:50:00', '2026-03-08 12:05:00')
                ->pluck('id')
                ->all(),
        )
        ->toContain($runningCompletedLog->id, $runningOpenLog->id)
        ->not->toContain($finishedLog->id)
        ->not->toContain($futureLog->id);

    Carbon::setTestNow();
});

it('defines the requested bookmark model contract', function () {
    $bookmark = new Bookmark;

    expect($bookmark->getFillable())->toBe([
        'session_hash',
        'article_id',
    ])
        ->and($bookmark->timestamps)->toBeFalse()
        ->and(Bookmark::UPDATED_AT)->toBeNull()
        ->and($bookmark->article())->toBeInstanceOf(BelongsTo::class);
});

it('defines the requested newsletter subscriber model contract', function () {
    $subscriber = new NewsletterSubscriber;

    expect($subscriber->getFillable())->toBe([
        'email',
        'name',
        'category_ids',
        'token',
        'confirmed',
        'confirmed_at',
        'unsubscribed_at',
        'ip_address',
        'country_code',
        'timezone',
        'locale',
    ])
        ->and($subscriber->getCasts())->toMatchArray([
            'category_ids' => 'array',
            'confirmed' => 'boolean',
            'confirmed_at' => 'datetime',
            'unsubscribed_at' => 'datetime',
        ]);
});

it('supports the requested newsletter subscriber scopes and token generation', function () {
    $activeSubscriber = NewsletterSubscriber::factory()->create([
        'confirmed' => true,
        'unsubscribed_at' => null,
    ]);
    $confirmedUnsubscribedSubscriber = NewsletterSubscriber::factory()->create([
        'confirmed' => true,
        'unsubscribed_at' => now(),
    ]);
    $pendingSubscriber = NewsletterSubscriber::factory()->create([
        'confirmed' => false,
        'unsubscribed_at' => null,
    ]);

    $subscriberWithoutToken = NewsletterSubscriber::factory()->create([
        'token' => '',
        'confirmed' => false,
        'unsubscribed_at' => now(),
    ]);

    expect(NewsletterSubscriber::confirmed()->pluck('id')->all())
        ->toContain($activeSubscriber->id, $confirmedUnsubscribedSubscriber->id)
        ->not->toContain($pendingSubscriber->id)
        ->and(NewsletterSubscriber::active()->pluck('id')->all())->toBe([$activeSubscriber->id])
        ->and($subscriberWithoutToken->token)->toHaveLength(64);
});
