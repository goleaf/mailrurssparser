<?php

namespace App\Models;

use Attla\EncodedAttributes\HasEncodedAttributes;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Scout\Searchable;

class Article extends Model
{
    use HasEncodedAttributes;

    /** @use HasFactory<\Database\Factories\ArticleFactory> */
    use HasFactory;

    use Searchable;
    use SoftDeletes;

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
        return $query->where('status', 'published')->where('published_at', '<=', now());
    }

    public function scopeDraft(Builder $query): Builder
    {
        return $query->where('status', 'draft');
    }

    public function scopeFeatured(Builder $query): Builder
    {
        return $query->where('is_featured', true);
    }

    public function scopeBreaking(Builder $query): Builder
    {
        return $query->where('is_breaking', true)->where('published_at', '>=', now()->subHours(24));
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', 'pending');
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

    public function scopeByContentType(Builder $query, string $type): Builder
    {
        return $query->where('content_type', $type);
    }

    public function scopeSearch(Builder $query, string $term): Builder
    {
        $term = trim($term);

        if ($term === '') {
            return $query;
        }

        $like = '%'.$term.'%';

        return $query->where(function (Builder $query) use ($like): void {
            $query->where('title', 'like', $like)
                ->orWhere('short_description', 'like', $like)
                ->orWhere('full_description', 'like', $like);
        });
    }

    public function scopeRelatedTo(Builder $query, Article $article, int $limit = 5): Builder
    {
        return $query
            ->where('category_id', $article->category_id)
            ->whereKeyNot($article->getKey())
            ->orderByDesc('published_at')
            ->limit($limit);
    }

    public function scopeTrending(Builder $query, int $hours = 24): Builder
    {
        return $query
            ->where('published_at', '>=', now()->subHours($hours))
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

    public function toSearchableArray(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'short_description' => $this->short_description,
            'full_description' => strip_tags((string) $this->full_description),
            'published_at' => $this->published_at?->timestamp,
            'category' => $this->relationLoaded('category') ? $this->category?->name : $this->category()->value('name'),
        ];
    }

    public function getReadingTimeTextAttribute(): string
    {
        return $this->reading_time.' мин чтения';
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
        return $this->published_at !== null && $this->published_at->gte(now()->subHours(6));
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    public function incrementViews(string $ipHash, string $sessionHash, array $meta = []): void
    {
        $hasRecentView = ArticleView::query()
            ->where('article_id', $this->id)
            ->where(function (Builder $query) use ($ipHash, $sessionHash): void {
                $query->where('ip_hash', $ipHash);

                if ($sessionHash !== '') {
                    $query->orWhere('session_hash', $sessionHash);
                }
            })
            ->where('viewed_at', '>=', now()->subHour())
            ->exists();

        if ($hasRecentView) {
            return;
        }

        $isUniqueView = ArticleView::query()
            ->where('article_id', $this->id)
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
            'device_type' => $meta['device_type'] ?? null,
            'referrer_type' => $meta['referrer_type'] ?? null,
            'referrer_domain' => $meta['referrer_domain'] ?? null,
            'ip_address' => $meta['ip_address'] ?? null,
            'session_id' => $meta['session_id'] ?? ($sessionHash !== '' ? $sessionHash : null),
            'user_agent' => $meta['user_agent'] ?? null,
            'referer' => $meta['referer'] ?? null,
            'viewed_at' => now(),
        ]);

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
                'name' => $this->source_name,
            ],
            'url' => rtrim((string) config('app.url'), '/').'#/articles/'.$this->slug,
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
            'canonical_url' => $this->canonical_url ?: rtrim((string) config('app.url'), '/').'#/articles/'.$this->slug,
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
