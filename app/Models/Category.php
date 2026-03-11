<?php

namespace App\Models;

use App\Services\StorageDisk;
use Attla\EncodedAttributes\HasEncodedAttributes;
use Awcodes\Curator\Models\Media as CuratorMedia;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use RalphJSmit\Laravel\SEO\Support\HasSEO;
use RalphJSmit\Laravel\SEO\Support\SEOData;
use Spatie\Image\Enums\Fit;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media as SpatieMedia;

class Category extends Model implements HasMedia
{
    use HasEncodedAttributes;

    /** @use HasFactory<\Database\Factories\CategoryFactory> */
    use HasFactory;

    use HasSEO;
    use InteractsWithMedia;

    /**
     * @var list<string>
     */
    protected $fillable = [
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
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'show_in_menu' => 'boolean',
        ];
    }

    public function subCategories(): HasMany
    {
        return $this->hasMany(SubCategory::class);
    }

    public function coverImage(): BelongsTo
    {
        return $this->belongsTo(CuratorMedia::class, 'curator_cover_id');
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('cover_image')
            ->useDisk(StorageDisk::Public->value)
            ->singleFile()
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp', 'image/svg+xml'])
            ->withResponsiveImages();
    }

    public function registerMediaConversions(?SpatieMedia $media = null): void
    {
        if ($media?->mime_type === 'image/svg+xml') {
            return;
        }

        $this->addMediaConversion('thumb')
            ->fit(Fit::Crop, 200, 200)
            ->format('webp')
            ->nonQueued();

        $this->addMediaConversion('banner')
            ->fit(Fit::Crop, 1200, 400)
            ->format('webp')
            ->queued();
    }

    public function activeSubCategories(): HasMany
    {
        return $this->subCategories()
            ->where('is_active', true)
            ->orderBy('order')
            ->orderBy('name');
    }

    public function articles(): HasMany
    {
        return $this->hasMany(Article::class);
    }

    public function rssFeeds(): HasMany
    {
        return $this->hasMany(RssFeed::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true)->orderBy('order');
    }

    public function scopeInMenu(Builder $query): Builder
    {
        return $query->where('show_in_menu', true);
    }

    public function scopeSearch(Builder $query, string $term): Builder
    {
        $term = trim($term);

        if ($term === '') {
            return $query;
        }

        $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $term).'%';

        return $query->where(function (Builder $query) use ($like): void {
            $query
                ->where('name', 'like', $like)
                ->orWhere('slug', 'like', $like)
                ->orWhere('rss_key', 'like', $like)
                ->orWhere('meta_title', 'like', $like);
        });
    }

    public function scopeForAdminIndex(Builder $query): Builder
    {
        return $query->withCount(['articles', 'subCategories', 'rssFeeds']);
    }

    /**
     * @return array<string, mixed>
     */
    public function getSeoData(): array
    {
        $title = $this->seo?->title ?: $this->getRawOriginal('meta_title') ?: $this->name;
        $description = $this->seo?->description ?: $this->getRawOriginal('meta_description') ?: Str::limit((string) $this->description, 160);
        $image = $this->seo?->image;
        $canonicalUrl = $this->seo?->canonical_url ?: route('category.show', ['slug' => $this->slug]);
        $robots = $this->seo?->robots ?: config('seo.robots.default', 'index, follow');

        return [
            'title' => $title,
            'description' => $description,
            'image' => $image,
            'robots' => $robots,
            'canonical_url' => $canonicalUrl,
            'og_title' => $title,
            'og_description' => $description,
            'og_image' => $image,
            'twitter_title' => $title,
            'twitter_description' => $description,
            'twitter_image' => $image,
            'meta_title' => $title,
            'meta_description' => $description,
        ];
    }

    public function getDynamicSEOData(): SEOData
    {
        $seoData = $this->getSeoData();

        return new SEOData(
            title: $seoData['title'],
            description: $seoData['description'],
            image: $seoData['image'],
            url: route('category.show', ['slug' => $this->slug]),
            site_name: config('app.name'),
            robots: $seoData['robots'],
            canonical_url: $seoData['canonical_url'],
            openGraphTitle: $seoData['og_title'],
        );
    }

    protected static function booted(): void
    {
        static::creating(function (Category $category): void {
            if (($category->slug === null || $category->slug === '') && $category->name !== null && $category->name !== '') {
                $category->slug = Str::slug($category->name);
            }
        });
    }
}
