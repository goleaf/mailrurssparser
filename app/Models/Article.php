<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Scout\Searchable;

class Article extends Model
{
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
        'title',
        'slug',
        'source_url',
        'source_guid',
        'image_url',
        'short_description',
        'full_description',
        'rss_content',
        'author',
        'source_name',
        'status',
        'is_featured',
        'is_breaking',
        'views_count',
        'reading_time',
        'published_at',
        'rss_parsed_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
            'rss_parsed_at' => 'datetime',
            'is_featured' => 'boolean',
            'is_breaking' => 'boolean',
            'views_count' => 'integer',
            'reading_time' => 'integer',
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

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class);
    }

    public function views(): HasMany
    {
        return $this->hasMany(ArticleView::class);
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
        return $query->where('is_breaking', true);
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

    public function scopeByTag(Builder $query, string $slug): Builder
    {
        return $query->whereHas('tags', function (Builder $query) use ($slug): void {
            $query->where('slug', $slug);
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

    public function toSearchableArray(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'short_description' => $this->short_description,
            'full_description' => $this->full_description,
            'published_at' => $this->published_at?->timestamp,
        ];
    }

    public function getReadingTimeTextAttribute(): string
    {
        return $this->reading_time.' мин чтения';
    }

    public function incrementViews(string $ip, ?string $sessionId = null): void
    {
        $threshold = now()->subHour();

        $hasRecentView = ArticleView::query()
            ->where('article_id', $this->id)
            ->where('ip_address', $ip)
            ->where('viewed_at', '>=', $threshold)
            ->exists();

        if ($hasRecentView) {
            return;
        }

        DB::table('articles')
            ->where('id', $this->id)
            ->increment('views_count');

        ArticleView::query()->create([
            'article_id' => $this->id,
            'ip_address' => $ip,
            'session_id' => $sessionId,
            'viewed_at' => now(),
        ]);
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
        });
    }
}
