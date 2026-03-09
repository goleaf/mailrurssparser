<?php

namespace App\Models;

use App\Services\ArticleContentType;
use App\Services\ArticleStatus;
use App\Services\MetricTracker;
use App\Services\TrackedMetric;
use Attla\EncodedAttributes\HasEncodedAttributes;
use DateTimeInterface;
use Filament\Forms\Components\RichEditor\MentionProvider;
use Filament\Forms\Components\RichEditor\Models\Concerns\InteractsWithRichContent;
use Filament\Forms\Components\RichEditor\Models\Contracts\HasRichContent;
use Filament\Forms\Components\RichEditor\TextColor;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Scout\Attributes\SearchUsingFullText;
use Laravel\Scout\Attributes\SearchUsingPrefix;
use Laravel\Scout\Searchable;

class Article extends Model implements HasRichContent
{
    use HasEncodedAttributes;

    /** @use HasFactory<\Database\Factories\ArticleFactory> */
    use HasFactory;

    use InteractsWithRichContent;
    use Searchable;
    use SoftDeletes;

    protected function setUpRichContent(): void
    {
        $this->registerRichContent('full_description')
            ->fileAttachmentsDisk('public')
            ->fileAttachmentsVisibility('public')
            ->mentions([
                self::categoryMentionProvider(),
                self::tagMentionProvider(),
            ])
            ->textColors([
                'mail-blue' => TextColor::make('Mail Blue', '#2563eb', darkColor: '#60a5fa'),
                'urgent-red' => TextColor::make('Urgent Red', '#dc2626', darkColor: '#f87171'),
                'market-green' => TextColor::make('Market Green', '#059669', darkColor: '#34d399'),
                ...TextColor::getDefaults(),
            ])
            ->customTextColors();
    }

    /**
     * @var list<string>
     */
    protected $fillable = [
        'category_id',
        'sub_category_id',
        'rss_feed_id',
        'editor_id',
        'title',
        'slug',
        'source_url',
        'source_guid',
        'image_url',
        'image_caption',
        'short_description',
        'full_description',
        'rss_content',
        'author',
        'author_url',
        'source_name',
        'status',
        'content_type',
        'is_featured',
        'is_breaking',
        'is_pinned',
        'is_editors_choice',
        'is_sponsored',
        'importance',
        'meta_title',
        'meta_description',
        'canonical_url',
        'structured_data',
        'views_count',
        'unique_views_count',
        'shares_count',
        'bookmarks_count',
        'reading_time',
        'engagement_score',
        'published_at',
        'rss_parsed_at',
        'last_edited_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ArticleStatus::class,
            'content_type' => ArticleContentType::class,
            'published_at' => 'datetime',
            'rss_parsed_at' => 'datetime',
            'last_edited_at' => 'datetime',
            'is_featured' => 'boolean',
            'is_breaking' => 'boolean',
            'is_pinned' => 'boolean',
            'is_editors_choice' => 'boolean',
            'is_sponsored' => 'boolean',
            'structured_data' => 'array',
            'views_count' => 'integer',
            'unique_views_count' => 'integer',
            'shares_count' => 'integer',
            'bookmarks_count' => 'integer',
            'reading_time' => 'integer',
            'engagement_score' => 'float',
            'importance' => 'integer',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function subCategory(): BelongsTo
    {
        return $this->belongsTo(SubCategory::class);
    }

    public function rssFeed(): BelongsTo
    {
        return $this->belongsTo(RssFeed::class);
    }

    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'editor_id');
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class)->withTimestamps();
    }

    public function relatedArticles(): BelongsToMany
    {
        return $this->belongsToMany(self::class, 'article_related_articles', 'article_id', 'related_article_id')
            ->withPivot([
                'score',
                'shared_tags_count',
                'shared_terms_count',
                'same_category',
                'same_sub_category',
                'same_content_type',
                'same_author',
                'same_source',
            ])
            ->withTimestamps();
    }

    public function views(): HasMany
    {
        return $this->hasMany(ArticleView::class);
    }

    public function bookmarkedBy(): HasMany
    {
        return $this->hasMany(Bookmark::class);
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query
            ->where('status', ArticleStatus::Published->value)
            ->where('published_at', '<=', now());
    }

    public function scopeDraft(Builder $query): Builder
    {
        return $query->where('status', ArticleStatus::Draft->value);
    }

    public function scopeFeatured(Builder $query): Builder
    {
        return $query->where('is_featured', true);
    }

    public function scopeBreaking(Builder $query): Builder
    {
        return $query->where('is_breaking', true)->where('published_at', '>=', now()->minus(hours: 24));
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', ArticleStatus::Pending->value);
    }

    public function scopePinned(Builder $query): Builder
    {
        return $query->where('is_pinned', true);
    }

    public function scopeEditorsChoice(Builder $query): Builder
    {
        return $query->where('is_editors_choice', true);
    }

    public function scopeByCategory(Builder $query, string $slug): Builder
    {
        return $query->whereHas('category', function (Builder $query) use ($slug): void {
            $query->where('slug', $slug);
        });
    }

    public function scopeBySubCategory(Builder $query, string $slug): Builder
    {
        return $query->whereHas('subCategory', function (Builder $query) use ($slug): void {
            $query->where('slug', $slug);
        });
    }

    public function scopeByTag(Builder $query, string|array $slugs): Builder
    {
        $slugs = Arr::wrap($slugs);

        return $query->whereHas('tags', function (Builder $query) use ($slugs): void {
            $query->whereIn('slug', $slugs);
        });
    }

    public function scopeByDateRange(Builder $query, string $from, string $to): Builder
    {
        return $query->whereBetween('published_at', [$from, $to]);
    }

    public function scopeByDate(Builder $query, string $date): Builder
    {
        return $query->whereDate('published_at', $date);
    }

    public function scopePublishedBetween(Builder $query, DateTimeInterface|string $from, DateTimeInterface|string $to): Builder
    {
        return $query
            ->published()
            ->whereBetween('published_at', [$from, $to]);
    }

    public function scopePublishedSince(Builder $query, DateTimeInterface|string $moment): Builder
    {
        return $query
            ->published()
            ->where('published_at', '>=', $moment);
    }

    public function scopeInCategory(Builder $query, Category|int $category): Builder
    {
        $categoryId = $category instanceof Category ? $category->getKey() : $category;

        return $query->where('category_id', $categoryId);
    }

    public function scopeFromFeed(Builder $query, RssFeed|int $feed): Builder
    {
        $feedId = $feed instanceof RssFeed ? $feed->getKey() : $feed;

        return $query->where('rss_feed_id', $feedId);
    }

    public function scopeByContentType(Builder $query, string|ArticleContentType $type): Builder
    {
        $contentType = ArticleContentType::fromValue($type);

        return $query->where('content_type', $contentType?->value ?? (string) $type);
    }

    public function scopeSearch(Builder $query, string $term): Builder
    {
        $term = trim($term);

        if ($term === '') {
            return $query;
        }

        $driver = $query->getConnection()->getDriverName();
        $likeOperator = $driver === 'pgsql' ? 'ilike' : 'like';
        $like = '%'.self::escapeLikeTerm($term).'%';

        return $query->where(function (Builder $query) use ($driver, $likeOperator, $like, $term): void {
            if (self::supportsFullTextDriver($driver)) {
                $query->whereFullText(
                    ['title', 'short_description', 'full_description', 'author', 'source_name'],
                    $term,
                );
            } else {
                $query->where('title', $likeOperator, $like)
                    ->orWhere('short_description', $likeOperator, $like)
                    ->orWhere('full_description', $likeOperator, $like)
                    ->orWhere('author', $likeOperator, $like)
                    ->orWhere('source_name', $likeOperator, $like);

                return;
            }

            $query->orWhere('title', $likeOperator, $like)
                ->orWhere('short_description', $likeOperator, $like)
                ->orWhere('full_description', $likeOperator, $like)
                ->orWhere('author', $likeOperator, $like)
                ->orWhere('source_name', $likeOperator, $like);
        });
    }

    public function scopeRelatedTo(Builder $query, Article $article, int $limit = 5): Builder
    {
        return $query
            ->inCategory($article->category_id)
            ->whereKeyNot($article->getKey())
            ->orderByDesc('published_at')
            ->limit($limit);
    }

    public function scopeTrending(Builder $query, int $hours = 24): Builder
    {
        return $query
            ->where('published_at', '>=', now()->minus(hours: $hours))
            ->orderByDesc('views_count');
    }

    public function scopePopular(Builder $query): Builder
    {
        return $query->orderByDesc('engagement_score');
    }

    public function scopeImportant(Builder $query, int $min = 7): Builder
    {
        return $query->where('importance', '>=', $min);
    }

    #[SearchUsingPrefix(['id', 'slug'])]
    #[SearchUsingFullText(['title', 'short_description', 'full_description', 'author', 'source_name'])]
    public function toSearchableArray(): array
    {
        $searchable = [
            'id' => $this->id,
            'slug' => $this->slug,
            'title' => $this->title,
            'short_description' => $this->short_description,
            'full_description' => (string) ($this->full_description ?? $this->rss_content ?? ''),
            'author' => $this->author,
            'source_name' => $this->source_name,
        ];

        if (config('scout.driver') !== 'database') {
            $searchable['full_description_plain'] = strip_tags(
                (string) ($this->full_description ?? $this->rss_content ?? ''),
            );
            $searchable['published_at_timestamp'] = $this->published_at?->timestamp;
            $searchable['status'] = $this->status?->value ?? $this->status;
            $searchable['content_type'] = $this->content_type?->value ?? $this->content_type;
            $searchable['is_breaking'] = $this->is_breaking;
            $searchable['is_pinned'] = $this->is_pinned;
            $searchable['engagement_score'] = $this->engagement_score;
        }

        return $searchable;
    }

    public function shouldBeSearchable(): bool
    {
        return $this->status === ArticleStatus::Published
            && $this->published_at !== null
            && $this->published_at->lte(now())
            && $this->deleted_at === null;
    }

    public function getReadingTimeTextAttribute(): string
    {
        return $this->reading_time.' мин чтения';
    }

    public static function sanitizeSourceName(?string $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $value = trim((string) preg_replace('/^[©\s]+/u', '', $value));
        $value = preg_replace('/^[\s"\'`«»„“”‚‘’]+|[\s"\'`«»„“”‚‘’]+$/u', '', $value) ?? $value;
        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;
        $value = preg_replace('/\s*-\s*Новости$/u', '', $value) ?? $value;
        $value = preg_replace('/\s+-\s+.+$/u', '', $value) ?? $value;
        $value = preg_replace('/^[\s"\'`«»„“”‚‘’]+|[\s"\'`«»„“”‚‘’]+$/u', '', $value) ?? $value;

        if ($value === '') {
            return null;
        }

        return in_array(Str::lower($value), self::genericSourceNames(), true)
            ? null
            : $value;
    }

    /**
     * @return array<int, string>
     */
    private static function genericSourceNames(): array
    {
        return [
            Str::lower((string) config('rss.feed_host', implode('.', ['news', 'mail', 'ru']))),
            Str::lower(implode('.', ['mail', 'ru'])),
        ];
    }

    private static function supportsFullTextDriver(string $driver): bool
    {
        return in_array($driver, ['mysql', 'pgsql'], true);
    }

    private static function escapeLikeTerm(string $term): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $term);
    }

    protected function sourceName(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value): ?string => static::sanitizeSourceName($value),
            set: fn (?string $value): ?string => static::sanitizeSourceName($value),
        );
    }

    public function getMetaTitleAttribute(?string $value): string
    {
        return $value ?: $this->title;
    }

    public function getMetaDescriptionAttribute(?string $value): string
    {
        return $value ?: Str::limit((string) $this->short_description, 160);
    }

    public function getContentAttribute(): string
    {
        return (string) ($this->full_description ?? $this->rss_content ?? '');
    }

    public function getIsRecentAttribute(): bool
    {
        return $this->published_at !== null && $this->published_at->gte(now()->minus(hours: 6));
    }

    protected static function categoryMentionProvider(): MentionProvider
    {
        return MentionProvider::make('@')
            ->searchPrompt('Начните вводить рубрику')
            ->noItemsMessage('Рубрики пока не созданы')
            ->getSearchResultsUsing(fn (string $search): array => Category::query()
                ->when(
                    filled($search),
                    fn (Builder $query): Builder => $query->where('name', 'like', "%{$search}%"),
                )
                ->orderBy('order')
                ->orderBy('name')
                ->limit(10)
                ->pluck('name', 'id')
                ->all())
            ->getLabelsUsing(fn (array $ids): array => Category::query()
                ->whereIn('id', $ids)
                ->pluck('name', 'id')
                ->all())
            ->url(function (string $id): ?string {
                $slug = Category::query()->whereKey($id)->value('slug');

                if (! filled($slug)) {
                    return null;
                }

                return route('category.show', ['slug' => $slug]);
            });
    }

    protected static function tagMentionProvider(): MentionProvider
    {
        return MentionProvider::make('#')
            ->searchPrompt('Начните вводить тег')
            ->noItemsMessage('Теги пока не созданы')
            ->getSearchResultsUsing(fn (string $search): array => Tag::query()
                ->when(
                    filled($search),
                    fn (Builder $query): Builder => $query->where('name', 'like', "%{$search}%"),
                )
                ->orderByDesc('usage_count')
                ->orderBy('name')
                ->limit(10)
                ->pluck('name', 'id')
                ->all())
            ->getLabelsUsing(fn (array $ids): array => Tag::query()
                ->whereIn('id', $ids)
                ->pluck('name', 'id')
                ->all())
            ->url(function (string $id): ?string {
                $slug = Tag::query()->whereKey($id)->value('slug');

                if (! filled($slug)) {
                    return null;
                }

                return route('tag.show', ['slug' => $slug]);
            });
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    public function incrementViews(string $ipHash, string $sessionHash, array $meta = []): void
    {
        $viewedAt = now();
        $hasRecentView = ArticleView::query()
            ->forArticle($this)
            ->matchingViewer($ipHash, $sessionHash)
            ->viewedSince($viewedAt->minus(hours: 1))
            ->exists();

        if ($hasRecentView) {
            return;
        }

        $isUniqueView = ArticleView::query()
            ->forArticle($this)
            ->where('ip_hash', $ipHash)
            ->doesntExist();

        DB::table('articles')
            ->where('id', $this->id)
            ->incrementEach([
                'views_count' => 1,
                'unique_views_count' => $isUniqueView ? 1 : 0,
            ]);

        ArticleView::query()->create([
            'article_id' => $this->id,
            'ip_hash' => $ipHash,
            'session_hash' => $sessionHash !== '' ? $sessionHash : null,
            'country_code' => $meta['country_code'] ?? null,
            'timezone' => $meta['timezone'] ?? null,
            'locale' => $meta['locale'] ?? null,
            'device_type' => $meta['device_type'] ?? null,
            'referrer_type' => $meta['referrer_type'] ?? null,
            'referrer_domain' => $meta['referrer_domain'] ?? null,
            'ip_address' => $meta['ip_address'] ?? null,
            'session_id' => $meta['session_id'] ?? ($sessionHash !== '' ? $sessionHash : null),
            'user_agent' => $meta['user_agent'] ?? null,
            'referer' => $meta['referer'] ?? null,
            'viewed_at' => $viewedAt,
        ]);

        app(MetricTracker::class)->record(
            TrackedMetric::ArticleView,
            measurable: $this,
            recordedAt: $viewedAt,
        );

        if ($isUniqueView) {
            app(MetricTracker::class)->record(
                TrackedMetric::ArticleUniqueView,
                measurable: $this,
                recordedAt: $viewedAt,
            );
        }

        $this->refresh();
    }

    public function incrementShares(): void
    {
        DB::table('articles')->where('id', $this->id)->increment('shares_count');
        $this->refresh();
    }

    public function recalculateEngagementScore(): void
    {
        $score = ($this->views_count * 1)
            + ($this->shares_count * 5)
            + ($this->bookmarks_count * 3)
            + ($this->importance * 10);

        DB::table('articles')
            ->where('id', $this->id)
            ->update(['engagement_score' => $score]);

        $this->refresh();
    }

    /**
     * @return array<string, mixed>
     */
    public function generateStructuredData(): array
    {
        $tagNames = $this->relationLoaded('tags')
            ? $this->tags->pluck('name')->implode(', ')
            : $this->tags()->pluck('name')->implode(', ');

        return [
            '@context' => 'https://schema.org',
            '@type' => 'NewsArticle',
            'headline' => $this->title,
            'description' => $this->short_description,
            'image' => $this->image_url,
            'datePublished' => $this->published_at?->toIso8601String(),
            'dateModified' => $this->updated_at?->toIso8601String(),
            'author' => [
                '@type' => 'Person',
                'name' => $this->author,
            ],
            'publisher' => [
                '@type' => 'Organization',
                'name' => $this->source_name ?: config('app.name'),
            ],
            'url' => route('articles.show', ['slug' => $this->slug]),
            'keywords' => $tagNames,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function getSeoData(): array
    {
        return [
            'meta_title' => $this->meta_title,
            'meta_description' => $this->meta_description,
            'canonical_url' => $this->canonical_url ?: route('articles.show', ['slug' => $this->slug]),
            'structured_data' => $this->structured_data ?? $this->generateStructuredData(),
            'image_url' => $this->image_url,
        ];
    }

    /**
     * @param  array<int, int>  $tagIds
     * @return array<string, array<int, int>>
     */
    public function syncTags(array $tagIds): array
    {
        $changes = $this->tags()->sync($tagIds);

        $affectedTagIds = array_values(array_unique(array_merge(
            $changes['attached'],
            $changes['detached'],
            $changes['updated'],
        )));

        if ($affectedTagIds === []) {
            return $changes;
        }

        Tag::query()
            ->whereIn('id', $affectedTagIds)
            ->withCount('articles')
            ->get()
            ->each(function (Tag $tag): void {
                $tag->update(['usage_count' => $tag->articles_count]);
            });

        return $changes;
    }

    protected static function booted(): void
    {
        static::creating(function (Article $article): void {
            if ($article->slug !== null && $article->slug !== '') {
                return;
            }

            $title = $article->title ?? '';
            $slug = Str::slug($title);

            if ($slug === '') {
                $slug = Str::slug($title, '-', 'ru');
            }

            if ($slug === '') {
                $slug = 'article-'.time();
            }

            $baseSlug = $slug;
            $suffix = 2;

            while (static::withTrashed()->where('slug', $slug)->exists()) {
                $slug = $baseSlug.'-'.$suffix;
                $suffix++;
            }

            $article->slug = $slug;
            $article->reading_time = static::calculateReadingTime((string) ($article->rss_content ?? $article->full_description ?? ''));
        });

        static::updating(function (Article $article): void {
            if ($article->isDirty(['full_description', 'rss_content'])) {
                $article->reading_time = static::calculateReadingTime((string) ($article->full_description ?? $article->rss_content ?? ''));
            }

            $article->last_edited_at = now();
        });

        static::deleted(function (Article $article): void {
            static::syncTagUsageCounts($article);
        });

        static::restored(function (Article $article): void {
            static::syncTagUsageCounts($article);
        });
    }

    private static function calculateReadingTime(string $content): int
    {
        $wordCount = str_word_count(strip_tags($content));

        return max(1, (int) ceil($wordCount / 200));
    }

    private static function syncTagUsageCounts(Article $article): void
    {
        $article->tags()
            ->withCount('articles')
            ->get()
            ->each(function (Tag $tag): void {
                $tag->update(['usage_count' => $tag->articles_count]);
            });
    }
}
